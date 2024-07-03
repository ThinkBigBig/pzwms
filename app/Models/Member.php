<?php

namespace App\Models;

use Illuminate\Http\Request;
use App\Models\BaseModel;

class Member extends BaseModel
{
    public $table = 'member';

    // const CREATEDTIME = 'createtime';
    // const UPDATEDTIME = 'updatetime';

     /**
     * 更新用户基本资料
     * @param type $username 用户名
     * @param type $oldpw 旧密码
     * @param type $newpw 新密码，如不修改为空
     * @param type $email Email，如不修改为空
     * @param type $ignoreoldpw 是否忽略旧密码
     * @param type $data 其他信息
     * @return boolean
     */
    public function userEdit($username, $oldpw, $newpw = '', $email = '', $ignoreoldpw = 0, $data = array())
    {
       

    }
}
