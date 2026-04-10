# 快麦云打印 PHP SDK 接入文档

## 对接流程

### 第一步：通过 Composer 引入 `kuaimai/php-kuaimai-core` 包

要求 PHP >= 8.0

```bash
composer require kuaimai/php-kuaimai-core
```

### 第二步：去快麦开放平台申请 appId 和 appSecret

前往快麦开放平台申请应用的 `appId` 和 `appSecret`。

### 第三步：初始化客户端

```php
<?php
use Kuaimai\KuaimaiClient;

$client = KuaimaiClient::createClient($appId, $appSecret);
```

### 第四步：初始化入参

以查询设备状态为例：

```php
<?php
use Kuaimai\Request\Device\QueryDeviceStatusRequest;

$statusReq = new QueryDeviceStatusRequest();
$statusReq->sns = json_encode([$testSn]);
```

### 第五步：调用 SDK

```php
$resp = $client->getAcsResponse($statusReq);
```

### SDK 代码 GitHub 地址

- PHP 云打印 Demo：`php-cloud-demo`
- PHP 核心 SDK：`php-kuaimai-core`（通过 Composer 引入）

---

## PHP SDK 特别说明

- PHP SDK 需要 **PHP >= 8.0**
- 通过 Composer 安装：`composer require kuaimai/php-kuaimai-core`
- PDF 打印功能需要安装 **Ghostscript**
- PHP SDK 使用**公共属性赋值**（不是 Java 的 setter 方法）
- 使用 `use` 语句引入对应的 Request 类，命名空间如下：
  - `Kuaimai\Request\Device\*`（设备相关：绑定、解绑、查询状态）
  - `Kuaimai\Request\Tspl\*`（标签打印相关）
  - `Kuaimai\Request\Esc\*`（小票打印相关）
  - `Kuaimai\Request\Misc\*`（其他：播报、取消任务、结果查询、菜鸟相关）

---

## 通用响应格式

所有接口返回统一的 JSON 格式：

```json
{
    "status": true,
    "data": {},
    "message": null,
    "code": null,
    "exceptionMessage": null
}
```

| 参数 | 类型 | 描述 |
| --- | --- | --- |
| status | Boolean | 请求是否成功 |
| data | Object | 返回数据 |
| message | String | 提示信息 |
| code | String | 错误码 |
| exceptionMessage | String | 异常信息 |

---

## API 列表

| API | 描述 |
| --- | --- |
| [BindDeviceRequest](#1-binddevicerequest---绑定设备) | 绑定设备 适用机型：所有快麦机型 |
| [UnbindDeviceRequest](#2-unbinddevicerequest---解绑设备) | 解绑设备 适用机型：所有快麦机型 |
| [QueryDeviceStatusRequest](#3-querydevicestatusrequest---查询设备状态) | 查询设备状态: 所有快麦机型 |
| [TsplTemplatePrintRequest](#4-tspltemplateprintrequest---标签模版打印间隙纸) | 标签模版-间隙纸打印 适用机型:KM118系列，KME31系列，KME41系列，KME20系列，KMSX系列 |
| [EscTemplatePrintRequest](#5-esctemplateprintrequest---小票模版打印连续纸) | 小票模版-连续纸打印 适用机型:KM118系列，KME31系列，KME41系列，KME20系列 |
| [TsplTemplateWriteRequest](#6-tspltemplatewriterequest---小票模版打印间隙纸) | 小票模版-间隙纸打印 适用机型:KM118系列，KME31系列，KME41系列，KME20系列 |
| [TsplXmlWriteRequest](#7-tsplxmlwriterequest---自定义-xml-打印间隙纸) | 自定义xml打印-间隙纸 适用机型:KM118系列，KME31系列，KME41系列，KME20系列 |
| [EscXmlWriteRequest](#8-escxmlwriterequest---自定义-xml-打印连续纸) | 自定义xml打印-连续纸 适用机型:KM118系列，KME31系列，KME41系列，KME20系列,KMDP系列，KMUP系列 |
| [TsplImageRequest](#9-tsplimagerequest---图片直接打印间隙纸) | 图片直接打印-间隙纸打印 适用机型:KM118系列，KME31系列，KME41系列 |
| [EscImageRequest](#10-escimagerequest---图片直接打印连续纸) | 图片直接打印-连续纸打印 适用机型：KM118系列，KME31系列，KME41系列 |
| [TsplPdfPrintRequest](#11-tsplpdfprintrequest---pdf-直接打印间隙纸) | pdf直接打印-间隙纸 适用机型:KM118系列，KME31系列，KME41系列 |
| [EscPdfPrintRequest](#12-escpdfprintrequest---pdf-直接打印连续纸) | pdf直接打印-连续纸 适用机型:KM118系列，KME31系列，KME41系列 |
| [ResultRequest](#13-resultrequest---打印任务结果查询) | 打印任务结果查询 适用机型：KM118系列，KME31系列，KME41系列，KME20系列 |
| [BroadcastRequest](#14-broadcastrequest---语音播报) | 语言播报 适用机型：KM118MGL,KME31GP |
| [CancelJobRequest](#15-canceljobrequest---取消待打印任务) | 取消待打印任务 适用机型：KM118系列，KME31系列，KME41系列，KME20系列 |
| [GetCainiaoCodeRequest](#16-getcainiaocoderequest---获取菜鸟云打印机-code) | KM360C获取云打印机code，code有效期时间为5分钟 |
| [CainiaoBindRequest](#17-cainiaobindrequest---菜鸟云打印机绑定) | KM360C绑定云打印机 |
| [CainiaoPrintRequest](#18-cainiaoprintrequest---菜鸟云打印) | KM360C图片打印 |

---

## 具体接口

### 1. BindDeviceRequest - 绑定设备

适用机型：所有快麦机型

#### 请求示例

```php
<?php
use Kuaimai\Request\Device\BindDeviceRequest;

$bindReq = new BindDeviceRequest();
$bindReq->sn = 'KM118DW123';
$bindReq->deviceKey = '606F8C';
$resp = $client->getAcsResponse($bindReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| deviceKey | String | 否 | 设备密钥，如果设备机身贴有密钥则必传 |
| bindName | String | 否 | 绑定名称 |
| noteName | String | 否 | 备注名称 |

#### 响应参数

| 参数 | 类型 | 描述 |
| --- | --- | --- |
| status | Boolean | 请求是否成功 |
| data | Object | 返回数据 |
| message | String | 提示信息 |
| code | String | 错误码 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 1000 | success | 成功 |
| 1001 | sn not found | 设备序列号不存在 |
| 1002 | device already bindName | 设备已绑定 |
| 1003 | device key error | 设备密钥错误 |
| 1004 | param error | 参数错误 |

---

### 2. UnbindDeviceRequest - 解绑设备

适用机型：所有快麦机型

#### 请求示例

```php
<?php
use Kuaimai\Request\Device\UnbindDeviceRequest;

$unbindReq = new UnbindDeviceRequest();
$unbindReq->sn = 'KM118DW123';
$unbindReq->deviceKey = '123456';
$resp = $client->getAcsResponse($unbindReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| deviceKey | String | 否 | 设备密钥 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 1000 | success | 成功 |
| 1001 | sn not found | 设备序列号不存在 |
| 1003 | device key error | 设备密钥错误 |
| 1004 | param error | 参数错误 |
| 1005 | device not bindName | 设备未绑定 |

---

### 3. QueryDeviceStatusRequest - 查询设备状态

适用机型：所有快麦机型

#### 请求示例

```php
<?php
use Kuaimai\Request\Device\QueryDeviceStatusRequest;

$statusReq = new QueryDeviceStatusRequest();
$statusReq->sns = json_encode(['KM118DW123']);
$resp = $client->getAcsResponse($statusReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sns | String | 是 | JSON 数组字符串，设备序列号列表，如 `["KM118DW123","KM118DW456"]` |

#### 响应参数

`data` 为数组，每个元素包含：

| 参数 | 类型 | 描述 |
| --- | --- | --- |
| sn | String | 设备序列号 |
| status | String | 设备状态：ONLINE（在线）、OFFLINE（离线）、UNACTIVE（未激活）、DISABLE（禁用） |

#### 响应示例

```json
{
    "status": true,
    "data": [
        {
            "sn": "KM118DW123",
            "status": "ONLINE"
        }
    ],
    "message": null,
    "code": null,
    "exceptionMessage": null
}
```

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 1000 | success | 成功 |
| 1004 | param error | 参数错误 |

---

### 4. TsplTemplatePrintRequest - 标签模版打印（间隙纸）

适用机型：KM118系列，KME31系列，KME41系列，KME20系列，KMSX系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;

$tsplTplReq = new TsplTemplatePrintRequest();
$tsplTplReq->sn = 'KM118DW123';
$tsplTplReq->templateId = 123;
$tsplTplReq->renderDataArray = '[{"table_test":[{"key_test":"3449394"}]}]';
$tsplTplReq->printTimes = 1;
$tsplTplReq->image = true;  // PHP SDK支持：先在本地渲染模板为图片再下发
$resp = $client->getAcsResponse($tsplTplReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| templateId | Number | 是 | 模板ID |
| renderDataArray | String | 是 | 渲染数据，JSON数组字符串 |
| image | Boolean | 否 | 是否先在本地渲染模板为图片再下发，PHP SDK 支持 |
| dpi | Number | 否 | 打印分辨率 |
| imei | String | 否 | KM360C 设备的 IMEI |
| printTimes | Number | 否 | 打印份数，默认1 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2003 | template not found | 模板不存在 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 5. EscTemplatePrintRequest - 小票模版打印（连续纸）

适用机型：KM118系列，KME31系列，KME41系列，KME20系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Esc\EscTemplatePrintRequest;

$escTplReq = new EscTemplatePrintRequest();
$escTplReq->sn = 'KM118DW123';
$escTplReq->templateId = 123;
$escTplReq->renderData = '{"table_test":[{"key_test":"3449394"}]}';
$resp = $client->getAcsResponse($escTplReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| templateId | Number | 是 | 模板ID |
| renderData | String | 是 | 渲染数据，JSON 字符串 |
| volume | Number | 否 | 音量 |
| volumeIndex | Number | 否 | 音量索引 |
| cut | Boolean | 否 | 是否切纸 |
| endFeed | Number | 否 | 打印结束走纸行数 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2003 | template not found | 模板不存在 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 6. TsplTemplateWriteRequest - 小票模版打印（间隙纸）

适用机型：KM118系列，KME31系列，KME41系列，KME20系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Tspl\TsplTemplateWriteRequest;

$tsplWriteReq = new TsplTemplateWriteRequest();
$tsplWriteReq->sn = 'KM118DW123';
$tsplWriteReq->templateId = 123;
$tsplWriteReq->renderData = '{"table_test":[{"key_test":"3449394"}]}';
$tsplWriteReq->printTimes = 2;
$resp = $client->getAcsResponse($tsplWriteReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| templateId | Number | 是 | 模板ID |
| renderData | String | 是 | 渲染数据，JSON 字符串 |
| printTimes | Number | 否 | 打印份数，默认1 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2003 | template not found | 模板不存在 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 7. TsplXmlWriteRequest - 自定义 XML 打印（间隙纸）

适用机型：KM118系列，KME31系列，KME41系列，KME20系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Tspl\TsplXmlWriteRequest;

$tsplXmlReq = new TsplXmlWriteRequest();
$tsplXmlReq->sn = 'KM118DW123';
$tsplXmlReq->xmlStr = '<page><render><t>hello,world</t></render></page>';
$tsplXmlReq->printTimes = 2;
$resp = $client->getAcsResponse($tsplXmlReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| xmlStr | String | 是 | XML 指令字符串 |
| jobs | Array | 否 | 多任务打印 |
| image | Boolean | 否 | 是否以图片方式下发 |
| printTimes | Number | 否 | 打印份数，默认1 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 8. EscXmlWriteRequest - 自定义 XML 打印（连续纸）

适用机型：KM118系列，KME31系列，KME41系列，KME20系列，KMDP系列，KMUP系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Esc\EscXmlWriteRequest;

$escXmlReq = new EscXmlWriteRequest();
$escXmlReq->sn = 'KM118DW123';
$escXmlReq->instructions = "<page><render><t size='01' feed='9'>hello,world</t></render></page>";
$resp = $client->getAcsResponse($escXmlReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| instructions | String | 是 | XML 指令字符串 |
| volume | Number | 否 | 音量 |
| volumeIndex | Number | 否 | 音量索引 |
| cut | Number | 否 | 切纸模式 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 9. TsplImageRequest - 图片直接打印（间隙纸）

适用机型：KM118系列，KME31系列，KME41系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Tspl\TsplImageRequest;

$tsplImgReq = new TsplImageRequest();
$tsplImgReq->sn = 'KM118DW123';
$tsplImgReq->imageBase64 = 'data:image/png;base64,...';
$tsplImgReq->printTimes = 1;
$resp = $client->getAcsResponse($tsplImgReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| imageBase64 | String | 是 | 图片 Base64 编码字符串 |
| setWidth | Number | 否 | 打印宽度（单位：mm） |
| setHeight | Number | 否 | 打印高度（单位：mm） |
| printTimes | Number | 否 | 打印份数，默认1 |
| dpi | Number | 否 | 打印分辨率 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 10. EscImageRequest - 图片直接打印（连续纸）

适用机型：KM118系列，KME31系列，KME41系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Esc\EscImageRequest;

$escImgReq = new EscImageRequest();
$escImgReq->sn = 'KM118DW123';
$escImgReq->imageBase64 = 'data:image/png;base64,...';
$escImgReq->endFeed = 3;
$resp = $client->getAcsResponse($escImgReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| imageBase64 | String | 是 | 图片 Base64 编码字符串 |
| endFeed | Number | 否 | 打印结束走纸行数 |
| printWidth | Number | 否 | 打印宽度（单位：mm） |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 11. TsplPdfPrintRequest - PDF 直接打印（间隙纸）

适用机型：KM118系列，KME31系列，KME41系列

> **PHP 特有说明：** PHP 端使用 Ghostscript 实现 PDF 转图片转换（Java 端使用 PDFBox）。请确保服务器已安装 Ghostscript：
> - CentOS: `yum install -y ghostscript`
> - Ubuntu: `apt-get install -y ghostscript`
> - macOS: `brew install ghostscript`

#### 请求示例

```php
<?php
use Kuaimai\Request\Tspl\TsplPdfPrintRequest;

// 单页打印
$tsplPdfReq = new TsplPdfPrintRequest();
$tsplPdfReq->sn = 'KM118DW123';
$tsplPdfReq->filePath = '/path/to/file.pdf';
$tsplPdfReq->dpi = 203;
$tsplPdfReq->width = 75.0;
$tsplPdfReq->height = 100.0;
$resp = $client->getAcsResponse($tsplPdfReq);

// 多页打印
$resp = $client->tsplPdfsPrint($tsplPdfReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| filePath | String | 是 | PDF 文件路径 |
| width | Number | 否 | 打印宽度（单位：mm） |
| height | Number | 否 | 打印高度（单位：mm） |
| dpi | Number | 否 | 打印分辨率 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 12. EscPdfPrintRequest - PDF 直接打印（连续纸）

适用机型：KM118系列，KME31系列，KME41系列

> **PHP 特有说明：** PHP 端使用 Ghostscript 实现 PDF 转图片转换（Java 端使用 PDFBox）。请确保服务器已安装 Ghostscript：
> - CentOS: `yum install -y ghostscript`
> - Ubuntu: `apt-get install -y ghostscript`
> - macOS: `brew install ghostscript`

#### 请求示例

```php
<?php
use Kuaimai\Request\Esc\EscPdfPrintRequest;

// 单页打印
$escPdfReq = new EscPdfPrintRequest();
$escPdfReq->sn = 'KM118DW123';
$escPdfReq->filePath = '/path/to/file.pdf';
$escPdfReq->printWidth = 58.0;
$escPdfReq->endFeed = 3;
$resp = $client->getAcsResponse($escPdfReq);

// 多页打印
$resp = $client->escPdfsPrint($escPdfReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| filePath | String | 是 | PDF 文件路径 |
| printWidth | Number | 否 | 打印宽度（单位：mm） |
| endFeed | Number | 否 | 打印结束走纸行数 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 13. ResultRequest - 打印任务结果查询

适用机型：KM118系列，KME31系列，KME41系列，KME20系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\ResultRequest;

$resultReq = new ResultRequest();
$resultReq->sn = 'KM118DW123';
$resultReq->jobIds = ['1718335259087'];
$resp = $client->getAcsResponse($resultReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| jobIds | Array | 是 | 任务ID列表 |

#### 响应参数

`data` 为数组，每个元素包含：

| 参数 | 类型 | 描述 |
| --- | --- | --- |
| jobId | String | 任务ID |
| desc | String | 结果描述 |
| code | Number | 结果码 |

#### 结果码说明

| code | 描述 |
| --- | --- |
| 2000 | 打印成功 |
| 2006 | 等待打印中 |
| 2007 | 打印失败 |
| 2004 | 异常 |

#### 响应示例

```json
{
    "status": true,
    "data": [
        {
            "jobId": "1718335259087",
            "desc": "success",
            "code": 2000
        }
    ],
    "message": null,
    "code": null,
    "exceptionMessage": null
}
```

---

### 14. BroadcastRequest - 语音播报

适用机型：KM118MGL，KME31GP

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\BroadcastRequest;

$broadcastReq = new BroadcastRequest();
$broadcastReq->sn = 'KM118MGL123';
$broadcastReq->volume = 80;
$broadcastReq->volumeContent = '测试语音播报';
$resp = $client->getAcsResponse($broadcastReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |
| volume | Number | 是 | 音量，范围 0-99 |
| volumeContent | String | 是 | 播报内容，最大50个字符 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 15. CancelJobRequest - 取消待打印任务

适用机型：KM118系列，KME31系列，KME41系列，KME20系列

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\CancelJobRequest;

$cancelReq = new CancelJobRequest();
$cancelReq->sn = 'KM118MGL123';
$resp = $client->getAcsResponse($cancelReq);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| sn | String | 是 | 设备序列号 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 16. GetCainiaoCodeRequest - 获取菜鸟云打印机 Code

KM360C 获取云打印机 code，code 有效期时间为5分钟。

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\GetCainiaoCodeRequest;

$req = new GetCainiaoCodeRequest();
$req->imei = '123';
$resp = $client->getAcsResponse($req);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| imei | String | 是 | 设备 IMEI |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 17. CainiaoBindRequest - 菜鸟云打印机绑定

KM360C 绑定云打印机。

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\CainiaoBindRequest;

$req = new CainiaoBindRequest();
$req->imei = '123';
$req->code = '7764';
$resp = $client->getAcsResponse($req);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| imei | String | 是 | 设备 IMEI |
| code | String | 是 | 通过 GetCainiaoCodeRequest 获取的 code |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

### 18. CainiaoPrintRequest - 菜鸟云打印

KM360C 图片打印。

#### 请求示例

```php
<?php
use Kuaimai\Request\Misc\CainiaoPrintRequest;

$req = new CainiaoPrintRequest();
$req->imei = '123';
$req->imageBase64Data = 'data:image/png;base64,...';
$resp = $client->getAcsResponse($req);
```

#### 请求参数

| 参数 | 类型 | 是否必填 | 描述 |
| --- | --- | --- | --- |
| imei | String | 是 | 设备 IMEI |
| imageBase64Data | String | 是 | 图片 Base64 编码字符串 |

#### 错误码

| code | message | 描述 |
| --- | --- | --- |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2004 | param error | 参数错误 |
| 2005 | system error | 系统错误 |

---

## XML 指令模板说明

以下 XML 指令模板适用于 `TsplXmlWriteRequest` 和 `EscXmlWriteRequest`，用于自定义打印内容。

### TSPL 标签打印 XML 指令

TSPL XML 指令用于间隙纸（标签纸）打印，通过 `TsplXmlWriteRequest` 下发。

#### 基本结构

```xml
<page width="40" height="30" direction="0" gap="2">
    <render>
        <!-- 打印元素 -->
    </render>
</page>
```

#### page 元素

页面根元素，定义标签纸尺寸和方向。

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| width | Number | 标签宽度（单位：mm） |
| height | Number | 标签高度（单位：mm） |
| direction | Number | 打印方向，0=正常，1=旋转180度 |
| gap | Number | 标签间隙（单位：mm） |

#### render 元素

渲染容器，包含具体的打印元素。

### ESC 小票打印 XML 指令

ESC XML 指令用于连续纸（小票纸）打印，通过 `EscXmlWriteRequest` 下发。

#### 基本结构

```xml
<page>
    <render>
        <!-- 打印元素 -->
    </render>
</page>
```

#### page 元素

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| width | Number | 打印宽度（单位：mm），可选 |

---

### 通用打印元素

以下打印元素在 TSPL 和 ESC 指令中通用（部分属性可能因指令类型不同而有差异）。

#### t 元素 - 文本

打印文本内容。

```xml
<t x="10" y="10" font="TSS24.BF2" size="01" feed="9">打印的文本内容</t>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | X 坐标（TSPL） |
| y | Number | Y 坐标（TSPL） |
| font | String | 字体名称（TSPL），如 TSS24.BF2 |
| size | String | 字体大小（ESC），如 00/01/10/11 |
| feed | Number | 走纸行数（ESC） |
| bold | Number | 加粗，0=不加粗，1=加粗 |
| underline | Number | 下划线，0=无，1=有 |
| align | String | 对齐方式（ESC），left/center/right |
| rotation | Number | 旋转角度（TSPL），0/90/180/270 |
| xMulti | Number | X 方向放大倍数（TSPL） |
| yMulti | Number | Y 方向放大倍数（TSPL） |

#### bc 元素 - 条形码

打印一维条形码。

```xml
<bc x="10" y="100" type="128" height="60" readable="1" narrow="2" wide="2">1234567890</bc>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | X 坐标（TSPL） |
| y | Number | Y 坐标（TSPL） |
| type | String | 条码类型，如 128/EAN13/EAN8/UPCA/39/93 等 |
| height | Number | 条码高度 |
| readable | Number | 是否显示可读文本，0=不显示，1=显示 |
| narrow | Number | 窄条宽度（TSPL） |
| wide | Number | 宽条宽度（TSPL） |
| rotation | Number | 旋转角度（TSPL），0/90/180/270 |
| align | String | 对齐方式（ESC），left/center/right |
| width | Number | 条码宽度（ESC） |
| hriPosition | Number | 文本位置（ESC），0=不打印/1=上方/2=下方/3=上下 |

#### qrc 元素 - 二维码

打印二维码（QR Code）。

```xml
<qrc x="200" y="10" level="L" width="6" mode="A">https://www.kuaimai.com</qrc>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | X 坐标（TSPL） |
| y | Number | Y 坐标（TSPL） |
| level | String | 纠错等级，L/M/Q/H |
| width | Number | 二维码单元格宽度 |
| mode | String | 编码模式，A=自动 |
| rotation | Number | 旋转角度（TSPL），0/90/180/270 |
| align | String | 对齐方式（ESC），left/center/right |
| size | Number | 二维码大小（ESC） |

#### img 元素 - 图片

打印图片。

```xml
<img x="10" y="10" width="100" height="100">data:image/png;base64,...</img>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | X 坐标（TSPL） |
| y | Number | Y 坐标（TSPL） |
| width | Number | 图片宽度 |
| height | Number | 图片高度 |
| align | String | 对齐方式（ESC），left/center/right |

> 图片内容为 Base64 编码字符串。

#### box 元素 - 矩形框

打印矩形框（TSPL）。

```xml
<box x="10" y="10" xEnd="200" yEnd="150" thickness="2"/>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | 起始 X 坐标 |
| y | Number | 起始 Y 坐标 |
| xEnd | Number | 结束 X 坐标 |
| yEnd | Number | 结束 Y 坐标 |
| thickness | Number | 线条粗细 |

#### circle 元素 - 圆形

打印圆形（TSPL）。

```xml
<circle x="100" y="100" diameter="80" thickness="2"/>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | 圆心 X 坐标 |
| y | Number | 圆心 Y 坐标 |
| diameter | Number | 直径 |
| thickness | Number | 线条粗细 |

#### ellipse 元素 - 椭圆

打印椭圆（TSPL）。

```xml
<ellipse x="100" y="100" width="120" height="80" thickness="2"/>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | 中心 X 坐标 |
| y | Number | 中心 Y 坐标 |
| width | Number | 宽度 |
| height | Number | 高度 |
| thickness | Number | 线条粗细 |

#### bar 元素 - 线条/填充矩形

打印填充矩形或线条（TSPL）。

```xml
<bar x="10" y="10" width="200" height="2"/>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| x | Number | 起始 X 坐标 |
| y | Number | 起始 Y 坐标 |
| width | Number | 宽度 |
| height | Number | 高度 |

#### pm 元素 - 打印模式

设置打印模式（ESC）。

```xml
<pm cut="1" endFeed="3"/>
```

| 属性 | 类型 | 描述 |
| --- | --- | --- |
| cut | Number | 切纸模式，0=不切纸，1=切纸 |
| endFeed | Number | 打印结束后走纸行数 |

---

### TSPL XML 完整示例

```xml
<page width="40" height="30" direction="0" gap="2">
    <render>
        <t x="10" y="10" font="TSS24.BF2" rotation="0" xMulti="1" yMulti="1">快麦云打印</t>
        <bc x="10" y="50" type="128" height="40" readable="1" narrow="2" wide="2" rotation="0">KM118DW123</bc>
        <qrc x="200" y="10" level="L" width="4" mode="A">https://www.kuaimai.com</qrc>
        <box x="5" y="5" xEnd="390" yEnd="230" thickness="2"/>
        <bar x="5" y="45" width="385" height="2"/>
        <img x="300" y="150" width="80" height="80">data:image/png;base64,...</img>
    </render>
</page>
```

### ESC XML 完整示例

```xml
<page>
    <render>
        <t size="11" bold="1" align="center" feed="3">快麦云打印小票</t>
        <t size="00" feed="2">--------------------------------</t>
        <t size="00" feed="2">商品名称：测试商品</t>
        <t size="00" feed="2">数量：1</t>
        <t size="00" feed="2">金额：99.00</t>
        <t size="00" feed="2">--------------------------------</t>
        <bc type="128" height="60" width="2" align="center" hriPosition="2">1234567890</bc>
        <qrc level="L" size="6" align="center">https://www.kuaimai.com</qrc>
        <pm cut="1" endFeed="3"/>
    </render>
</page>
```

---

## 全局错误码

| code | message | 描述 |
| --- | --- | --- |
| 1000 | success | 成功 |
| 1001 | sn not found | 设备序列号不存在 |
| 1002 | device already bindName | 设备已绑定 |
| 1003 | device key error | 设备密钥错误 |
| 1004 | param error | 参数错误 |
| 1005 | device not bindName | 设备未绑定 |
| 2000 | success | 成功 |
| 2001 | device offline | 设备离线 |
| 2002 | device not bindName | 设备未绑定 |
| 2003 | template not found | 模板不存在 |
| 2004 | param error / 异常 | 参数错误或异常 |
| 2005 | system error | 系统错误 |
| 2006 | waiting | 等待打印中 |
| 2007 | print failed | 打印失败 |
