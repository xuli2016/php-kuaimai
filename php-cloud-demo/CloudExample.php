<?php

/**
 * CloudExample.php
 *
 * Kuaimai Cloud Print SDK PHP 示例文件
 * 对应 Java 版本 CloudExample.java
 *
 * 使用前提：
 *   1. 在 https://open.iot.kuaimai.com/#/home 注册应用，获取 appId 和 appSecret
 *   2. 获取打印机序列号 (SN)
 *   3. 建议将凭证存入环境变量，不要硬编码在代码中
 *
 * 运行方式：
 *   composer install
 *   php CloudExample.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Kuaimai\KuaimaiClient;
use Kuaimai\Bean\ResponseEnvelope;
use Kuaimai\Request\Device\BindDeviceRequest;
use Kuaimai\Request\Device\UnbindDeviceRequest;
use Kuaimai\Request\Device\QueryDeviceStatusRequest;
use Kuaimai\Request\Device\AdjustDeviceDensityRequest;
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;
use Kuaimai\Request\Tspl\TsplTemplateWriteRequest;
use Kuaimai\Request\Tspl\TsplXmlWriteRequest;
use Kuaimai\Request\Tspl\TsplImageRequest;
use Kuaimai\Request\Esc\EscTemplatePrintRequest;
use Kuaimai\Request\Esc\EscXmlWriteRequest;
use Kuaimai\Request\Esc\EscImageRequest;
use Kuaimai\Request\Misc\ResultRequest;
use Kuaimai\Request\Misc\CancelJobRequest;
use Kuaimai\Request\Misc\BroadcastRequest;
use Kuaimai\Request\Misc\GetCainiaoCodeRequest;
use Kuaimai\Request\Misc\CainiaoBindRequest;
use Kuaimai\Request\Misc\CainiaoPrintRequest;
use Kuaimai\Request\Tspl\TsplPdfPrintRequest;
use Kuaimai\Request\Esc\EscPdfPrintRequest;

// ---------------------------------------------------------------------------
// 凭证配置（推荐从环境变量读取，避免硬编码）
// ---------------------------------------------------------------------------
// 建议使用：
//   export KUAIMAI_APP_ID="your_app_id"
//   export KUAIMAI_APP_SECRET="your_app_secret"

$appId     = getenv('KUAIMAI_APP_ID')     ?: '你的appId';
$appSecret = getenv('KUAIMAI_APP_SECRET') ?: '你的appSecret';
$testSn    = '你的序列号';

// ---------------------------------------------------------------------------
// 初始化客户端
// ---------------------------------------------------------------------------
$client = KuaimaiClient::createClient($appId, $appSecret);

// ---------------------------------------------------------------------------
// 1. 绑定设备
// ---------------------------------------------------------------------------
/* 
$bindReq = new BindDeviceRequest();
$bindReq->sn        = $testSn;
$bindReq->deviceKey = '606F8C';
$resp = $client->getAcsResponse($bindReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 2. 解绑设备
// ---------------------------------------------------------------------------
/* 
$unbindReq = new UnbindDeviceRequest();
$unbindReq->sn        = $testSn;
$unbindReq->deviceKey = '606F8C';
$resp = $client->getAcsResponse($unbindReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 3. 查询设备状态（支持批量，传 sns JSON 数组字符串）
// ---------------------------------------------------------------------------
/* 
$statusReq = new QueryDeviceStatusRequest();
$statusReq->sns = json_encode([$testSn]);
$resp = $client->getAcsResponse($statusReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 4. TSPL 标签模板打印（间隙纸标签打印机）
//    renderDataArray: JSON 数组字符串，支持批量渲染
//    image: true 表示 PHP SDK 先在本地渲染模板为图片，再下发
// ---------------------------------------------------------------------------
$tsplTplReq = new TsplTemplatePrintRequest();
$tsplTplReq->sn              = $testSn;
$tsplTplReq->templateId      = 1634989639;
$tsplTplReq->renderDataArray = '[{"table_test":[{"key_test":"3449394"}]}]';
$tsplTplReq->printTimes      = 1;
$tsplTplReq->image           = true;

// $resp = $client->getAcsResponse($tsplTplReq);
// echo $resp->toJson() . PHP_EOL;

// ---------------------------------------------------------------------------
// 5. TSPL 小票模板打印（间隙纸）
//    renderData: 单个对象 JSON 字符串
// ---------------------------------------------------------------------------
/* 
$tsplWriteReq = new TsplTemplateWriteRequest();
$tsplWriteReq->sn         = $testSn;
$tsplWriteReq->templateId = 1634992959;
$tsplWriteReq->renderData = '{"table_test":[{"key_test":"3449394"}]}';
$tsplWriteReq->printTimes = 1;
$resp = $client->getAcsResponse($tsplWriteReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 6. TSPL 自定义 XML 打印（间隙纸）
// ---------------------------------------------------------------------------
/* 
$tsplXmlReq = new TsplXmlWriteRequest();
$tsplXmlReq->sn         = $testSn;
$tsplXmlReq->xmlStr     = '<page><render width="100" height="150"><t x="20" y="20" font="0" xMultiple="3" yMultiple="3" bold="1">生产加工单</t><box x="10" y="200" xEnd="770" yEnd="1160" thickness="2" radius="0"/><bc x="300" y="200" codeType="128" height="100" style="1" rotate="0" narrow="2" wide="2">123456</bc></render></page>';
$tsplXmlReq->printTimes = 1;
$resp = $client->getAcsResponse($tsplXmlReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 7. TSPL 直接图片打印（间隙纸）
//    支持 Base64 图片字符串（可带 data:image/png;base64, 前缀）
// ---------------------------------------------------------------------------
/* 
$tsplImgReq = new TsplImageRequest();
$tsplImgReq->sn          = $testSn;
$tsplImgReq->imageBase64 = 'data:image/png;base64,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GKvCMIZ+ff/yyy/LM888U957773wkIWte3668soryxlnnNF1A6C33nprgGm199JLL5Xx48eHLrfddls54IADyrzKm2z+GMvkku42dnU4wA99rqiy3xyY0V999VXEWFl17bXXjjg7ooq1IyoGURTLxN62qtyxCmMAhv3888/FM75nTNM+v8+rrndUh0nhEdo4uKtJMvYTTzwR91ZZZZVyzz33xMaO+5hLJKyPP/643HLLLTHJxx13XIz52WeflW233bZgNaARgE6DKX0y1aDhJgsoxl3FrO233z7iE2My3qZh9Yoy2AIiDee6AJM8sJSrz5gxvTr/Ef0CHXjvvvtueEB9Xz4DwiRstNFGsdXoWmZwYUSiEgouvvjicvfdd5f3338/XF9I+PTTT4uQBdSlIf2CmiyliNgEEAni22+/DcOTVRl3tcPAdGuuDYwhQ4aWiRMnxucMDyZCO31iGYC++eabAFz/aknXAILpr776apRGJ554Yhk3flwAnAsAY+rPZCmvjj/++HL/ffdHaPH97bff7gop2g629Atq/eBYwrUnT55cf7nrc8ZVIAHOehwQGIpdsvB+++0X7Z9++ulw50suuSTYdeGFFwZD77rrrnLNNdeUTTfdNJIiT9lxxx3LvffeGxPi4ZzAmHDroeo/y2Zt07VN5H7771defvnlCFMYyms8C1Rnzw+WNDxtqQxwKe/wmYIMwhTMtARkBMMAavGAQW+88UbYIB7uueeeETpcuOCCCwLsyy67LGIiN3YcccQRRfxcd911y6OPPhoTqv19990X8ZY+yTrjq1WxUiw2Ns9Yf/31o+RSRplknkW0H0xpGFRKmF2GDK3cmeI+B2MqJesB1pYRDGOsAlxW3njjjbuYrhi/+uqrywknnBAAn3XWWeXoo4+O+5ILQIH/+eeflyuuuKJIPOS0006LkPHmG29GSZbjJ/sAm7LOOuvEpCvBOud1doUAoA4mUxeb/VPB7uecbcp1V1BMFMeEAiHjzTffjDpxzTXXjIwsoWyw/gZldLXASMGiWrL6qjr/Ul5//fVy7rnnRgl3yCGHlEsvvTQYfP3115dnn302woUiH/AmNFlLl3Rzfb/44ovl+eefD9aqANarNlbq2+f4rTw3xdT6gRlRz1QZWFIR/5Rb6k3VwT777BMujGGMwV6h4PEnHo82al0iVIiNf/01qwBfYlTU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';  // 替换为实际 Base64
$tsplImgReq->printTimes  = 1;
// 可选：指定标签尺寸（mm），默认从图片像素自动计算
// $tsplImgReq->setWidth  = 75.0;
// $tsplImgReq->setHeight = 100.0;
$resp = $client->getAcsResponse($tsplImgReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 8. 小票模板打印（连续纸）
// ---------------------------------------------------------------------------
/* 
$escTplReq = new EscTemplatePrintRequest();
$escTplReq->sn          = $testSn;
$escTplReq->templateId  = 1634992959;
$escTplReq->renderData  = '{"table_test":[{"key_test":"3449394"}]}';
$resp = $client->getAcsResponse($escTplReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 9. ESC/POS 自定义 XML 打印（连续纸）
// ---------------------------------------------------------------------------
/* 
$escXmlReq = new EscXmlWriteRequest();
$escXmlReq->sn           = $testSn;
$escXmlReq->instructions = "<page><render><t size='01' feed='9'>hello,world</t></render></page>";
// $escXmlReq->cut = 1;  // 1表示切刀，不传为不切
$resp = $client->getAcsResponse($escXmlReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 10. ESC/POS 直接图片打印（连续纸）
// ---------------------------------------------------------------------------
/* 
$escImgReq = new EscImageRequest();
$escImgReq->sn          = $testSn;
$escImgReq->imageBase64 = 'data:image/png;base64,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GKvCMIZ+ff/yyy/LM888U957773wkIWte3668soryxlnnNF1A6C33nprgGm199JLL5Xx48eHLrfddls54IADyrzKm2z+GMvkku42dnU4wA99rqiy3xyY0V999VXEWFl17bXXjjg7ooq1IyoGURTLxN62qtyxCmMAhv3888/FM75nTNM+v8+rrndUh0nhEdo4uKtJMvYTTzwR91ZZZZVyzz33xMaO+5hLJKyPP/643HLLLTHJxx13XIz52WeflW233bZgNaARgE6DKX0y1aDhJgsoxl3FrO233z7iE2My3qZh9Yoy2AIiDee6AJM8sJSrz5gxvTr/Ef0CHXjvvvtueEB9Xz4DwiRstNFGsdXoWmZwYUSiEgouvvjicvfdd5f3338/XF9I+PTTT4uQBdSlIf2CmiyliNgEEAni22+/DcOTVRl3tcPAdGuuDYwhQ4aWiRMnxucMDyZCO31iGYC++eabAFz/aknXAILpr776apRGJ554Yhk3flwAnAsAY+rPZCmvjj/++HL/ffdHaPH97bff7gop2g629Atq/eBYwrUnT55cf7nrc8ZVIAHOehwQGIpdsvB+++0X7Z9++ulw50suuSTYdeGFFwZD77rrrnLNNdeUTTfdNJIiT9lxxx3LvffeGxPi4ZzAmHDroeo/y2Zt07VN5H7771defvnlCFMYyms8C1Rnzw+WNDxtqQxwKe/wmYIMwhTMtARkBMMAavGAQW+88UbYIB7uueeeETpcuOCCCwLsyy67LGIiN3YcccQRRfxcd911y6OPPhoTqv19990X8ZY+yTrjq1WxUiw2Ns9Yf/31o+RSRplknkW0H0xpGFRKmF2GDK3cmeI+B2MqJesB1pYRDGOsAlxW3njjjbuYrhi/+uqrywknnBAAn3XWWeXoo4+O+5ILQIH/+eeflyuuuKJIPOS0006LkPHmG29GSZbjJ/sAm7LOOuvEpCvBOud1doUAoA4mUxeb/VPB7uecbcp1V1BMFMeEAiHjzTffjDpxzTXXjIwsoWyw/gZldLXASMGiWrL6qjr/Ul5//fVy7rnnRgl3yCGHlEsvvTQYfP3115dnn302woUiH/AmNFlLl3Rzfb/44ovl+eefD9aqANarNlbq2+f4rTw3xdT6gRlRz1QZWFIR/5Rb6k3VwT777BMujGGMwV6h4PEnHo82al0iVIiNf/01qwBfYlTU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';  // 替换为实际 Base64

$resp = $client->getAcsResponse($escImgReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 11. 查询打印结果（通过 Job ID）
// ---------------------------------------------------------------------------
/* 
$resultReq = new ResultRequest();
$resultReq->sn     = $testSn;
$resultReq->jobIds = ['1774959527841'];
$resp = $client->getAcsResponse($resultReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 12. 取消打印任务
// ---------------------------------------------------------------------------
/* 
$cancelReq = new CancelJobRequest();
$cancelReq->sn = $testSn;
$resp = $client->getAcsResponse($cancelReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 13. 语音播报（部分型号支持）
// ---------------------------------------------------------------------------
/* 
$broadcastReq = new BroadcastRequest();
$broadcastReq->sn            = $testSn;
$broadcastReq->volume        = 80;
$broadcastReq->volumeContent = '测试语音播报';
$resp = $client->getAcsResponse($broadcastReq);
echo $resp->toJson() . PHP_EOL;
*/


// ---------------------------------------------------------------------------
// 14. 调整打印浓度（支持 KM118/KME31/KME41 系列，范围 1-15，默认 8）
// ---------------------------------------------------------------------------
/* 
$densityReq = new AdjustDeviceDensityRequest();
$densityReq->sn      = $testSn;
$densityReq->density = 9;
$resp = $client->getAcsResponse($densityReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 15. KM360C 云打印机操作（菜鸟集成）
// ---------------------------------------------------------------------------

// 15a. 获取绑定码（有效期 5 分钟）
/* 
$cainiaoCodeReq = new GetCainiaoCodeRequest();
$cainiaoCodeReq->imei = '123';
$resp = $client->getAcsResponse($cainiaoCodeReq);
echo $resp->toJson() . PHP_EOL;
*/

// 15b. 绑定云打印机
/* 
$cainiaoBindReq = new CainiaoBindRequest();
$cainiaoBindReq->imei = '123';
$cainiaoBindReq->code = '7764';
$resp = $client->getAcsResponse($cainiaoBindReq);
echo $resp->toJson() . PHP_EOL;
*/

// 15c. 云打印机图片打印
/*
$cainiaoPrintReq = new CainiaoPrintRequest();
$cainiaoPrintReq->imei           = '123';
$cainiaoPrintReq->imageBase64Data = 'data:image/png;base64,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GKvCMIZ+ff/yyy/LM888U957773wkIWte3668soryxlnnNF1A6C33nprgGm199JLL5Xx48eHLrfddls54IADyrzKm2z+GMvkku42dnU4wA99rqiy3xyY0V999VXEWFl17bXXjjg7ooq1IyoGURTLxN62qtyxCmMAhv3888/FM75nTNM+v8+rrndUh0nhEdo4uKtJMvYTTzwR91ZZZZVyzz33xMaO+5hLJKyPP/643HLLLTHJxx13XIz52WeflW233bZgNaARgE6DKX0y1aDhJgsoxl3FrO233z7iE2My3qZh9Yoy2AIiDee6AJM8sJSrz5gxvTr/Ef0CHXjvvvtueEB9Xz4DwiRstNFGsdXoWmZwYUSiEgouvvjicvfdd5f3338/XF9I+PTTT4uQBdSlIf2CmiyliNgEEAni22+/DcOTVRl3tcPAdGuuDYwhQ4aWiRMnxucMDyZCO31iGYC++eabAFz/aknXAILpr776apRGJ554Yhk3flwAnAsAY+rPZCmvjj/++HL/ffdHaPH97bff7gop2g629Atq/eBYwrUnT55cf7nrc8ZVIAHOehwQGIpdsvB+++0X7Z9++ulw50suuSTYdeGFFwZD77rrrnLNNdeUTTfdNJIiT9lxxx3LvffeGxPi4ZzAmHDroeo/y2Zt07VN5H7771defvnlCFMYyms8C1Rnzw+WNDxtqQxwKe/wmYIMwhTMtARkBMMAavGAQW+88UbYIB7uueeeETpcuOCCCwLsyy67LGIiN3YcccQRRfxcd911y6OPPhoTqv19990X8ZY+yTrjq1WxUiw2Ns9Yf/31o+RSRplknkW0H0xpGFRKmF2GDK3cmeI+B2MqJesB1pYRDGOsAlxW3njjjbuYrhi/+uqrywknnBAAn3XWWeXoo4+O+5ILQIH/+eeflyuuuKJIPOS0006LkPHmG29GSZbjJ/sAm7LOOuvEpCvBOud1doUAoA4mUxeb/VPB7uecbcp1V1BMFMeEAiHjzTffjDpxzTXXjIwsoWyw/gZldLXASMGiWrL6qjr/Ul5//fVy7rnnRgl3yCGHlEsvvTQYfP3115dnn302woUiH/AmNFlLl3Rzfb/44ovl+eefD9aqANarNlbq2+f4rTw3xdT6gRlRz1QZWFIR/5Rb6k3VwT777BMujGGMwV6h4PEnHo82al0iVIiNf/01qwBfYlTU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';  // 替换为实际 Base64

$resp = $client->getAcsResponse($cainiaoPrintReq);
echo $resp->toJson() . PHP_EOL;
*/

// 15d. KM360C TSPL 模板打印（使用 imei 代替 sn）
/* 
$km360cTplReq = new TsplTemplatePrintRequest();
$km360cTplReq->imei            = '123';
$km360cTplReq->templateId      = 1634989639;
$km360cTplReq->renderDataArray = '[{"table_test":[{"key_test":"3449394"}]}]';
$resp = $client->getAcsResponse($km360cTplReq);
echo $resp->toJson() . PHP_EOL;
*/

// ===========================================================================
// PDF 打印（需要 Imagick 扩展 或 Ghostscript 命令行工具）
//
// 对应 Java 版本中使用 PDFBox 将 PDF 转换为图片后走图片打印接口的逻辑。
// PHP 端使用 Imagick 或 Ghostscript (gs) 实现同样的 PDF→图片 转换。
//
// 安装 Ghostscript：
//   CentOS: yum install -y ghostscript
//   Ubuntu: apt-get install -y ghostscript
//   macOS:  brew install ghostscript
// ===========================================================================

// ---------------------------------------------------------------------------
// 16. PDF 直接打印（间隙纸） —— 只打印第一页
// ---------------------------------------------------------------------------
/* 
$tsplPdfReq = new TsplPdfPrintRequest();
$tsplPdfReq->sn       = $testSn;
$tsplPdfReq->filePath = '/Users/admin/java/project/guangyun/demo/123.pdf';   // 替换为实际 PDF 文件路径
$tsplPdfReq->dpi      = 203;                    // 203 或 300
$tsplPdfReq->width    = 75.0;                   // 标签宽度（mm）
$tsplPdfReq->height   = 100.0;                  // 标签高度（mm）
$resp = $client->getAcsResponse($tsplPdfReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 17. PDF 多页打印（间隙纸） —— 打印所有页，每页一张标签
// ---------------------------------------------------------------------------
/* 
$tsplPdfReq = new TsplPdfPrintRequest();
$tsplPdfReq->sn       = $testSn;
$tsplPdfReq->filePath = '/Users/admin/java/project/guangyun/demo/123.pdf';   // 替换为实际 PDF 文件路径
$tsplPdfReq->dpi      = 203;
$tsplPdfReq->width    = 75.0;
$tsplPdfReq->height   = 100.0;
$resp = $client->tsplPdfsPrint($tsplPdfReq);    // 注意：多页打印直接调用方法，不走 getAcsResponse
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 18. PDF 直接打印（连续纸） —— 只打印第一页
// ---------------------------------------------------------------------------
/* 
$escPdfReq = new EscPdfPrintRequest();
$escPdfReq->sn         = $testSn;
$escPdfReq->filePath   = '/Users/admin/java/project/guangyun/demo/123.pdf';  // 替换为实际 PDF 文件路径
$escPdfReq->printWidth = 58.0;                  // 打印宽度（mm），默认 58
$escPdfReq->endFeed    = 3;                     // 打印后走纸行数
$resp = $client->getAcsResponse($escPdfReq);
echo $resp->toJson() . PHP_EOL;
*/

// ---------------------------------------------------------------------------
// 19. PDF 多页打印（连续纸） —— 打印所有页 
// ---------------------------------------------------------------------------
/* 
$escPdfReq = new EscPdfPrintRequest();
$escPdfReq->sn         = $testSn;
$escPdfReq->filePath   = '/Users/admin/java/project/guangyun/demo/123.pdf';  // 替换为实际 PDF 文件路径
$escPdfReq->printWidth = 58.0;
$escPdfReq->endFeed    = 3;
$resp = $client->escPdfsPrint($escPdfReq);      // 注意：多页打印直接调用方法，不走 getAcsResponse
echo $resp->toJson() . PHP_EOL;
*/

