<?php

namespace App\Models\Admin\V2;

use App\Models\AdminUser;
use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsQualityDetail extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_quality_detail';

    const NOTMAL = 1;
    const DEFECT = 2;

    protected $appends = ['quality_type_txt',];

    public function getQualityTypeTxtAttribute(): string
    {
        return [self::DEFECT => '瑕疵', self::NOTMAL => '正品'][$this->quality_type] ?? '';
    }

    static function getQualityTypeByLevel($level)
    {
        if ($level == 'A') return self::NOTMAL;
        return self::DEFECT;
    }

    function arrivalRegist()
    {
        return $this->hasOne(ArrivalRegist::class, 'id', 'arr_id');
    }

    function specBar()
    {
        return $this->hasOne(ProductSpecAndBar::class, 'bar_code', 'bar_code');
    }

    function adminUser()
    {
        return $this->hasOne(AdminUser::class, 'id', 'admin_user_id');
    }

    function qualityList()
    {
        return $this->hasOne(WmsQualityList::class, 'qc_code', 'qc_code');
    }
}
