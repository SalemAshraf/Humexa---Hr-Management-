<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceEvent;
use App\Modules\Attendance\Models\AttendanceLocation;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Employees\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttendanceMobileAction
{
    public function record(Employee $employee, string $type, array $data, ?UploadedFile $selfie): AttendanceRecord
    {
        $tenantId = (string) tenant('id');
        $policy = AttendancePolicy::query()->where('tenant_id', $tenantId)->firstOrFail();
        if (!$policy->mobile_checkin_enabled) throw ValidationException::withMessages(['attendance'=>'Mobile attendance is disabled by company policy.']);
        if ($policy->location_verification_enabled && (!isset($data['latitude'], $data['longitude'], $data['accuracy']))) throw ValidationException::withMessages(['location'=>'Location coordinates and accuracy are required.']);
        if ($policy->selfie_verification_enabled && !$selfie) throw ValidationException::withMessages(['selfie'=>'A selfie is required by company policy.']);

        $location = null;
        if ($policy->location_verification_enabled) {
            if ((int)$data['accuracy'] > $policy->location_accuracy_limit) throw ValidationException::withMessages(['accuracy'=>'GPS accuracy is outside the company limit.']);
            $locations = AttendanceLocation::query()->where('tenant_id',$tenantId)->where('is_active',true)->get();
            $location = $locations->first(fn($loc)=>$this->distanceMeters((float)$data['latitude'],(float)$data['longitude'],(float)$loc->latitude,(float)$loc->longitude) <= $loc->radius_meters);
            if (!$location) throw ValidationException::withMessages(['location'=>'You are outside an approved attendance location.']);
        }

        $storedSelfie = $selfie ? $selfie->store('tenants/'.$tenantId.'/attendance/selfies','local') : null;
        try {
            return DB::transaction(function() use ($employee,$tenantId,$type,$data,$storedSelfie,$location) {
                $employee = Employee::query()->where('tenant_id',$tenantId)->whereKey($employee->id)->lockForUpdate()->firstOrFail();
                $date = now()->toDateString();
                $record = AttendanceRecord::query()->where('tenant_id',$tenantId)->where('employee_id',$employee->id)->whereDate('attendance_date',$date)->lockForUpdate()->first();
                if ($type === 'check_in') {
                    if ($record && $record->check_in_at) throw ValidationException::withMessages(['attendance'=>'Check-in already recorded for today.']);
                    $record ??= AttendanceRecord::create(['tenant_id'=>$tenantId,'employee_id'=>$employee->id,'attendance_date'=>$date,'status'=>'recorded']);
                    $record->check_in_at = now();
                    $record->check_in_source = 'mobile';
                } else {
                    if (!$record || !$record->check_in_at) throw ValidationException::withMessages(['attendance'=>'Check-in must be recorded before check-out.']);
                    if ($record->check_out_at) throw ValidationException::withMessages(['attendance'=>'Check-out already recorded for today.']);
                    $record->check_out_at = now();
                    $record->check_out_source = 'mobile';
                    $record->worked_minutes = max(0,$record->check_in_at->diffInMinutes($record->check_out_at));
                }
                $record->save();
                AttendanceEvent::create([
                    'tenant_id'=>$tenantId,'attendance_record_id'=>$record->id,'employee_id'=>$employee->id,
                    'event_type'=>$type,'source'=>'mobile','occurred_at'=>now(),
                    'latitude'=>$data['latitude']??null,'longitude'=>$data['longitude']??null,
                    'location_accuracy'=>$data['accuracy']??null,'location_id'=>$location?->id,
                    'media_path'=>$storedSelfie,'metadata'=>['client_timestamp'=>$data['client_timestamp']??null],
                ]);
                return $record->fresh();
            },3);
        } catch (\Throwable $e) {
            if ($storedSelfie) Storage::disk('local')->delete($storedSelfie);
            throw $e;
        }
    }

    private function distanceMeters(float $lat1,float $lon1,float $lat2,float $lon2):float
    {
        $earth=6371000; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
        $a=sin($dLat/2)**2+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        $a = min(1.0, max(0.0, $a));
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}