<div>
            <div style="width: 80%;margin-left:10%;">
              <div
                style="width: 100%;text-align: center;color:black;display:flex;justify-content: center;">
                <div style="border: 1px solid;">
                  <div style="width:512px;">
                    <div style="text-align: center;">
                      <div>
                        <img src="data:image/png;base64,{{ $info['barcode'] }}" alt="Barcode" style="text-align: center;margin-top:20px;" >
                        <div style="margin-top:8px;">{{$info['dispatch_num']}}</div>
                        </div>
                    </div>
                    <div style="text-align: center;font-size:22px;font-wight:700;margin:1em 0">
                      {{ $info['order_no'] }}
                    </div>
                    <div style="text-align: center;font-size:22px;font-wight:700;margin:1em 0">
                      {{ $info['product_name'] }}
                    </div>
                    <div style="font-size:22px;font-wight:700;margin:1em 0">
                      <div style="display:inline-block;text-align:center;padding:0 2%">{{ $info['size'] }} &nbsp;&nbsp; {{ $info['product_sn'] }}</div>
                    </div>
                    <div style="text-align: center;font-size:22px;font-wight:700;margin:1em 0">{{ $info['time'] }} </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>