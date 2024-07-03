<!DOCTYPE html>
<html lang="en">

<head>
</head>

<body>
    <table style="border-collapse: collapse; width: 900px; height: 56px; font-family: SimHei; font-size: 18px; position: relative;" id="pdf">
        <tbody>
            <tr style="text-align:left;" class="firstRow">
                <td style="text-align:left;" colspan="5">
                    {{$data['order']['order_num'] ?? ''}}
                </td>
            </tr>
            <tr style="height:50px;text-align:center;" class="firstRow">
                <td style="font-weight: bold;font-size:40px;text-align:center;" colspan="5">
                    買取申込書
                </td>
            </tr>
            <tr style="height:75px;text-align:center;" class="firstRow">
                <td style="text-align:left;">
                    <img src="static/img/pdflogo.png" />
                </td>
                <td style="text-align:right">
                    {{ date('Y').'年'.date('m').'月'.date('d').'日'}}
                </td>
            </tr>
            <tr style="height:100px;">
                <td style="border:2px solid;" colspan="5">
                    <table style="width:100%;border:0px;text-align:center;" border="1" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="width:20%;background:#C0C0C0;">ふりがな</td>
                            <td style="width:30%">{{$data['examine']['name'] ?? ''}}</td>
                            <td style="width:10%;background:#C0C0C0;">性別</td>
                            <td style="width:10%">{{$data['examine']['sex']==1 ?'男':'女' }}</td>
                            <td style="width:30%;background:#C0C0C0;">生年月日</td>
                        </tr>

                        <tr>
                            <td style="background:#C0C0C0;">お名前</td>s
                            <td>{{$data['examine']['name'] ?? ''}}</td>
                            <td style="background:#C0C0C0;">年齢</td>
                            <td>{{$data['examine']['age'] ?? ''}}才</td>
                            <td>西暦 {{$data['examine']['birth'] ?? ''}}</td>
                        </tr>
                        <tr>
                            <td style="background:#C0C0C0;">ご住所</td>
                            <td colspan="4" style="text-align: left;">〒{{$data['examine']['addr'] ?? ''}}</td>
                        </tr>

                        <tr>
                            <td style="background:#C0C0C0;">お電話番号</td>
                            <td>{{$data['member']['mobile'] ?? ''}}</td>
                            <td colspan="2" rowspan="2" style="background:#C0C0C0;">ご職業</td>
                            <td rowspan="2">{{$data['examine']['occupation'] ?? ''}}</td>
                        </tr>

                        <tr>
                            <td style="background:#C0C0C0;">メールアドレス</td>
                            <td>{{$data['member']['email'] ?? ''}}</td>
                        </tr>

                    </table>
                </td>
            </tr>
            <table style="width:100%;border:0px;text-align:center;" cellspacing="0" cellpadding="0">
                <tr style="height:32px;text-align:left;">
                    <td style="text-align:left;">【必要本人確認種類】</td>
                    <td style="text-align:left"><span style="color:red;font-size:10px;">※申込者本人の身分証に限ります<br>※古物営業法により取引相手の義務付けられている為、本人確認種類は氏名、現住所、生年月日が記載されているものをご用意下さい。</span></td>
                </tr>
            </table>
            <tr style="height:200px">
                <td style="border:2px solid;" colspan="5">
                    <table style="width:100%;border:0px;text-align:center;" border="1" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="text-align:left;background:#C0C0C0;border:0px;" colspan="3">■銀行振込の場合</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;" colspan="3">
                                &nbsp;&nbsp;□住民票の写し（原本）&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                □印鑑証明書（原本）&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                □戸籍の謄本（原本）
                            </td>
                        </tr>
                        <tr>
                            <td style="background:#C0C0C0;" colspan="2">お振込先銀行名</td>
                            <td style="background:#C0C0C0;">支店名</td>
                        </tr>
                        <tr>
                            <td colspan="2">{{$data['examine']['bank_name'] ?? ''}}</td>
                            <td>{{$data['examine']['branch_name'] ?? ''}}</td>
                        </tr>
                        <tr>
                            <td style="width:20%;background:#C0C0C0;">口座種別</td>
                            <td style="width:40%;background:#C0C0C0;">口座番号</td>
                            <td style="width:40%;background:#C0C0C0;">口座名義</td>
                        </tr>

                        <tr>
                            <td>{{$data['examine']['port_category'] ?? ''}}</td>
                            <td>{{$data['examine']['slogans'] ?? ''}}</td>
                            <td>{{$data['examine']['name_mouth'] ?? ''}}</td>
                        </tr>
                        <tr>
                            <td style="text-align:left;background:#C0C0C0;border:0px;" colspan="3">■现金書留の場合<span style="color:red;font-size:10px;">※ご本人限定受取にょる现金書留とをります</span></td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align:left;">
                                &nbsp;□運転免許証のコピー（両面）
                                &nbsp;□パスポートのコピー（写真及び住所記載欄)
                                &nbsp;□健康保険証のコピー<br>
                                &nbsp;□官公庁及び特殊法人の身分証明書のコピー
                                &nbsp;□在留カードのコピー（両面）
                                &nbsp;□マイナンバーカードのコピー（両面）
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align:left;background:#C0C0C0;border:0px;" colspan="3">■法人の場合</td>
                        </tr>

                        <tr>
                            <td colspan="3" style="text-align:left;">
                                &nbsp;&nbsp;□登記事項証明書（原本）
                                &nbsp;&nbsp;□印鑑登録証明書（原本）
                                &nbsp;&nbsp;<span style="color:red;font-size:10px;">※発行より3ヶ月以内のも※乙担当者横O名刺一枚必要汇涂D末寸。</span>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
            <tr style="height:32px">
                <td style="font-weight: bold;" colspan="5">
                    【商品内容】
                </td>
            </tr>
            <tr style="height:170px">

                <td style="border:2px solid;" colspan="5">
                    <table style="width:100%;border:0px;text-align:center;" border="1" cellspacing="0" cellpadding="0">
                        <tr>
                            <td style="width:20%;background:#C0C0C0;">商品名</td>
                            <td style="width:20%;background:#C0C0C0;">品番</td>
                            <td style="width:10%;background:#C0C0C0;">状態</td>
                            <td style="width:15%;background:#C0C0C0;">サイズ</td>
                            <td style="width:15%;background:#C0C0C0;">査定値段</td>
                            <td style="width:10%;background:#C0C0C0;">数量</td>
                            <td style="width:10%;background:#C0C0C0;">合計</td>
                        </tr>
                        <?php echo $data['html']; ?>
                        <tr>
                            <td style="color:red;font-size:10px;text-align:left;" colspan="4"></td>
                            <td>総合計</td>
                            <td>{{$data['order']['num'] ?? ''}}</td>
                            <td>￥{{number_format($data['order']['price']) ?? ''}}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="5">&nbsp;</td>
            </tr>
            <tr style="height:120px;border:1px solid;">
                <td colspan="5" style="font-size:12px;">
                    <div><b style="color:red;">【ご確認事項】</b></div>
                    <div>・18才未満のお客様は買取行っていません。</div>
                    <div>・盗難品、偽造品は買取できません。（上記事項に該当したばあい返金、損害賠償請求を行い、警察署へ被害届を提出致します。）</div>
                    <div>・ご本人確認のため、身分証を確認致します。（確認できない場合お引き取りできません。）</div>
                    <div>・買取成立後、（お振込後）のご返品は出来ませんので、ご注意ください。</div>
                    <div>・配送時の傷や破損、紛失は一切保証出来ません。</div>
                    <div>（尚、不正転売目的にて入手した商品の買取を行うことは出来兼ねます。何卒ご了承ください。）</div>
                    <div>・お客様の個人情報の保護に関する法律、その他法令を尊守し、また「個人情報保護方針」を定め、個人情報の適切な取り扱いを行います。</div>
                    <div>・お客様にご提供頂きました本同意書の個人情報は、下記目的のために利用致します。</div>
                    <div>①買取申込・同意の確認及び所有者の確認。②古物営業法に則った売買履歴の管理。</div>

                </td>
            </tr>
            <tr>
                <td colspan="5">&nbsp;</td>
            </tr>
            <tr style="height:80px;text-align:left;font-size:13px;">
                <td style="width:60%">
                    <b style="text-decoration:underline;">上記に同意頂ける場合は右欄にご署名下さい。</b><br />
                    ※ご同意頂けない場合は、お申込頂けませんので、ご了承下さい。<br />
                    ※万が一、上記の処理がされてない場合は、返品させて頂きます。<br />
                    その場合の送料は、ご負担頂きますので、ご注意下さい。<br />
                    <b>送付先</b>〒171-0014東京都豊島区池袋2-24-8安楽ビル101室<br />
                    　　　　　　　THE 1 SNEAKER<br />
                    　　　　　　　TEL:03-6914-0602
                    FAX:03-6914-0602
                    <br />
                </td>
                <td style="text-align:right;font-weight:bold;width:40%">
                    ご署名欄 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br />
                    {{$data['examine']['signature'] ?? ''}}
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>