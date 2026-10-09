<?php

namespace AbsenShalat\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const ATTENDANCE_SCAN_START = 'attendance_scan_start';

    public const ATTENDANCE_SCAN_END = 'attendance_scan_end';

    protected $table = 'app_settings';

    public $timestamps = false;

    protected $fillable = ['key', 'value'];
}