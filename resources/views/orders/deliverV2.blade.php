<div>
  <div style="height: 800px;"></div>
    <div style="width: 80%;margin-left:10%;">
      <div style="width: 100%;text-align: center;color:black;">
        <div>
          <div style="width:512px;position: absolute;left: 50%;bottom: 14px;transform:translate(-50%,0)">
            <div style="text-align:center;padding:2px 2%;font-size:14px;">オーダ番号 {{ $info['order_no'] }}</div>
            <div style="text-align:center;padding:2px 2%;font-size:14px;">品番 {{ $info['product_sn'] }}</div>
            <div style="text-align:center;padding:2px 2%;font-size:14px;">サイズ {{ $info['size'] }} </div>

            <div style="text-align:center;padding:0 2%;font-size:29px;font-weight:bold;">ヤマト追跡番号 </div>
            <div style="text-align: center;">
              <div>
                <img src="data:image/png;base64,{{ $info['barcode'] }}" alt="Barcode"
                  style="text-align: center;margin-top:20px;">
                <div style="margin-top:8px;">{{$info['dispatch_num']}}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>