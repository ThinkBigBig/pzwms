<?php

namespace App\Models\Admin\V2;

use App\Models\Admin\wmsBaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WmsSupplierDocument extends wmsBaseModel
{
    use HasFactory;
    protected $guarded = [];
    public $table = 'wms_supplier_documents';

    protected $appends = ['type_txt', 'passport_type_txt'];

    // 证件类型 1-个人番号卡 2-保险证 3-在留卡 4-护照 5-免许证
    const TYPE_PERSONAL = 1;
    const TYPE_INSURANCE = 2;
    const TYPE_STAY = 3;
    const TYPE_PASSPORT = 4;
    const TYPE_EXEMPTION = 5;

    static $type_map = [
        self::TYPE_PERSONAL => '个人番号卡',
        self::TYPE_INSURANCE => '保险证',
        self::TYPE_STAY => '在留卡',
        self::TYPE_PASSPORT => '护照',
        self::TYPE_EXEMPTION => '免许证',
    ];

    public function getTypeTxtAttribute(): string
    {
        return self::$type_map[$this->type] ?? '';
    }

    // 护照类型 1-普通护照 2-公务护照 3-外交护照
    const PASSPORT_NORAML = 1;
    const PASSPORT_BUSINESS = 2;
    const PASSPORT_DIPLOMACY = 3;

    static $passport_map = [
        self::PASSPORT_NORAML => '普通护照',
        self::PASSPORT_BUSINESS => '公务护照',
        self::PASSPORT_DIPLOMACY => '外交护照',
    ];

    public function getPassportTypeTxtAttribute(): string
    {
        return self::$passport_map[$this->passport_type] ?? '';
    }

    
}
