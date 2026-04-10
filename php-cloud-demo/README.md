# 快麦云打印 PHP SDK 使用指南

本项目是快麦云打印 PHP SDK 的示例工程，包含所有 API 接口的调用示例。

拿到本项目后，按照下面的步骤即可快速完成对接。

---

## 一、准备工作

### 1.1 注册开放平台

前往 [快麦开放平台](https://open.iot.kuaimai.com/#/home) 注册应用，获取：

- **appId**（应用 ID）
- **appSecret**（应用密钥）

### 1.2 获取打印机信息

- **SN**（序列号）：打印机背面标签上的序列号
- **deviceKey**（设备密钥）：打印机背面标签上的密钥（绑定设备时需要）

### 1.3 服务器环境要求

| 要求 | 说明 |
|---|---|
| PHP | >= 8.0 |
| PHP 扩展 | curl、gd、zlib、json（json 为 PHP 8.0+ 内置） |
| Composer | PHP 包管理工具 |
| Ghostscript | **PDF 打印功能必需**，用于将 PDF 转换为图片 |

### 1.4 服务器依赖安装

**以下为客户服务器上需要安装的完整清单：**

#### CentOS / RHEL

```bash
# 1. PHP 扩展（如缺少）
yum install -y php-gd php-curl php-json php-zlib

# 2. Ghostscript（PDF 打印必需）
yum install -y ghostscript

# 3. 中文字体（模板本地渲染 image=true 时必需）
yum install -y wqy-microhei-fonts wqy-zenhei-fonts

# 4. 安装 Composer（如未安装）
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 5. 重启 php-fpm（如使用）
systemctl restart php-fpm
```

#### Ubuntu / Debian

```bash
# 1. PHP 扩展（如缺少）
apt-get install -y php-gd php-curl php-json php-zlib

# 2. Ghostscript（PDF 打印必需）
apt-get install -y ghostscript

# 3. 中文字体（模板本地渲染 image=true 时必需）
apt-get install -y fonts-wqy-microhei fonts-wqy-zenhei

# 4. 安装 Composer（如未安装）
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 5. 重启 php-fpm（如使用）
systemctl restart php-fpm
```

#### macOS（开发环境）

```bash
brew install ghostscript
```

#### 验证安装

```bash
php -v                          # PHP 版本 >= 8.0
php -m | grep -E "curl|gd|zlib" # 应输出 curl、gd、zlib
gs --version                    # Ghostscript 版本号
fc-list :lang=zh                # 中文字体列表（有输出即可）
composer --version               # Composer 版本
```

---

## 二、快速开始

### 2.1 项目结构

收到的文件结构如下，**两个目录需要放在同一层级**：

```
your-workspace/
├── php-kuaimai-core/     # SDK 核心库（不需要改动）
└── php-cloud-demo/       # 示例工程（在此基础上开发）
    ├── composer.json
    ├── CloudExample.php   # 所有接口调用示例
    └── README.md          # 本文档
```

### 2.2 安装依赖

```bash
cd php-cloud-demo
composer install
```

> `composer install` 会自动从同级目录引入 `php-kuaimai-core`，并下载其依赖的二维码库、条形码库等。

### 2.3 配置凭证

**推荐方式：通过环境变量配置（避免密钥泄露）**

```bash
export KUAIMAI_APP_ID="你的appId"
export KUAIMAI_APP_SECRET="你的appSecret"
```

也可以直接修改 `CloudExample.php` 中的变量：

```php
$appId     = '你的appId';
$appSecret = '你的appSecret';
$testSn    = '你的打印机SN';
```

### 2.4 运行示例

```bash
php CloudExample.php
```

默认执行的是 **TSPL 模板打印** 示例。要测试其他功能，打开 `CloudExample.php`，取消对应代码段的注释即可。

---

## 三、API 接口一览

### 3.1 设备管理

| 功能 | Request 类 | 调用方式 | 说明 |
|---|---|---|---|
| 绑定设备 | `BindDeviceRequest` | `getAcsResponse` | 需要 sn + deviceKey |
| 解绑设备 | `UnbindDeviceRequest` | `getAcsResponse` | 需要 sn + deviceKey |
| 查询设备状态 | `QueryDeviceStatusRequest` | `getAcsResponse` | 支持批量，传 sns JSON 数组 |
| 调整打印浓度 | `AdjustDeviceDensityRequest` | `getAcsResponse` | 范围 1-15，默认 8 |

### 3.2 TSPL 标签打印（间隙纸打印机）

| 功能 | Request 类 | 调用方式 | 说明 |
|---|---|---|---|
| 模板打印 | `TsplTemplatePrintRequest` | `getAcsResponse` | 最常用，支持批量 + 本地渲染 |
| 票据模板打印 | `TsplTemplateWriteRequest` | `getAcsResponse` | 单条数据渲染 |
| XML 自定义打印 | `TsplXmlWriteRequest` | `getAcsResponse` | 传入自定义 XML 指令 |
| 图片直接打印 | `TsplImageRequest` | `getAcsResponse` | 传入 Base64 图片 |
| PDF 直接打印 | `TsplPdfPrintRequest` | `getAcsResponse` | 打印 PDF 第一页 |
| PDF 多页打印 | `TsplPdfPrintRequest` | `tsplPdfsPrint` | 打印 PDF 所有页 |

### 3.3 ESC/POS 小票打印（连续纸打印机）

| 功能 | Request 类 | 调用方式 | 说明 |
|---|---|---|---|
| 模板打印 | `EscTemplatePrintRequest` | `getAcsResponse` | 小票模板渲染打印 |
| XML 自定义打印 | `EscXmlWriteRequest` | `getAcsResponse` | 传入自定义 XML 指令 |
| 图片直接打印 | `EscImageRequest` | `getAcsResponse` | 传入 Base64 图片 |
| PDF 直接打印 | `EscPdfPrintRequest` | `getAcsResponse` | 打印 PDF 第一页 |
| PDF 多页打印 | `EscPdfPrintRequest` | `escPdfsPrint` | 打印 PDF 所有页 |

### 3.4 其他功能

| 功能 | Request 类 | 调用方式 | 说明 |
|---|---|---|---|
| 查询打印结果 | `ResultRequest` | `getAcsResponse` | 通过 jobId 查询 |
| 取消打印任务 | `CancelJobRequest` | `getAcsResponse` | 取消该打印机所有排队任务 |
| 语音播报 | `BroadcastRequest` | `getAcsResponse` | 部分型号支持，音量 1-100 |

### 3.5 KM360C 云打印机（菜鸟集成）

| 功能 | Request 类 | 调用方式 | 说明 |
|---|---|---|---|
| 获取绑定码 | `GetCainiaoCodeRequest` | `getAcsResponse` | 有效期 5 分钟 |
| 绑定云打印机 | `CainiaoBindRequest` | `getAcsResponse` | 需要 imei + code |
| 云打印机图片打印 | `CainiaoPrintRequest` | `getAcsResponse` | 传入 Base64 图片 |

---

## 四、调用方式说明

所有接口的调用方式完全一致，三步即可：

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Kuaimai\KuaimaiClient;
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;

// 1. 创建客户端（单例，整个进程只需创建一次）
$client = KuaimaiClient::createClient('你的appId', '你的appSecret');

// 2. 构建请求对象，设置参数
$req = new TsplTemplatePrintRequest();
$req->sn              = '打印机SN';
$req->templateId      = 1634989639;     // 模板 ID（在开放平台创建）
$req->renderDataArray = '[{"table_test":[{"key_test":"3449394"}]}]';  // 渲染数据
$req->printTimes      = 1;              // 打印份数

// 3. 发送请求，获取响应
$resp = $client->getAcsResponse($req);
echo $resp->toJson();
```

**响应格式：**

```json
{
    "status": true,
    "data": { ... },
    "message": null,
    "code": 100
}
```

| 字段 | 类型 | 说明 |
|---|---|---|
| status | bool | true=成功，false=失败 |
| code | int | 100=成功，200=参数错误，600=不存在，700=非法操作，900=系统错误 |
| message | string | 错误时的提示信息 |
| data | mixed | 返回数据（不同接口返回不同内容） |

---

## 五、常用场景示例

### 5.1 TSPL 模板打印（最常用）

```php
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;

$req = new TsplTemplatePrintRequest();
$req->sn              = $testSn;
$req->templateId      = 1634989639;
$req->renderDataArray = '[{"table_test":[{"key_test":"3449394"}]}]';
$req->printTimes      = 1;
$req->image           = true;   // true=本地渲染为图片后打印（推荐），false=服务端渲染

$resp = $client->getAcsResponse($req);
echo $resp->toJson();
```

> **`image` 参数说明：**
> - `true`：SDK 先从服务端获取模板，在本地渲染为图片，再发送给打印机。适合需要精确控制打印效果的场景。
> - `false`：直接由服务端渲染并下发，更省本地资源。

### 5.2 批量打印（一次请求打印多张标签）

`renderDataArray` 是 JSON 数组，数组中每个元素对应一张标签：

```php
$req->renderDataArray = json_encode([
    ["table_test" => [["key_test" => "订单001", "key_name" => "张三"]]],
    ["table_test" => [["key_test" => "订单002", "key_name" => "李四"]]],
    ["table_test" => [["key_test" => "订单003", "key_name" => "王五"]]],
]);
```

### 5.3 图片直接打印

```php
use Kuaimai\Request\Tspl\TsplImageRequest;

// 读取图片文件并转为 Base64
$imageBase64 = base64_encode(file_get_contents('/path/to/label.png'));

$req = new TsplImageRequest();
$req->sn          = $testSn;
$req->imageBase64 = $imageBase64;
$req->printTimes  = 1;
// 可选：指定标签尺寸（毫米），不指定则从图片像素自动计算
// $req->setWidth  = 75.0;
// $req->setHeight = 100.0;

$resp = $client->getAcsResponse($req);
```

### 5.4 PDF 直接打印（间隙纸，打印第一页）

```php
use Kuaimai\Request\Tspl\TsplPdfPrintRequest;

$req = new TsplPdfPrintRequest();
$req->sn       = $testSn;
$req->filePath = '/path/to/箱标.pdf';   // PDF 文件路径
$req->dpi      = 203;                    // 203 或 300
$req->width    = 75.0;                   // 标签宽度（mm）
$req->height   = 100.0;                  // 标签高度（mm）

$resp = $client->getAcsResponse($req);
echo $resp->toJson();
```

### 5.5 PDF 多页打印（间隙纸，打印所有页）

```php
use Kuaimai\Request\Tspl\TsplPdfPrintRequest;

$req = new TsplPdfPrintRequest();
$req->sn       = $testSn;
$req->filePath = '/path/to/箱标.pdf';
$req->dpi      = 203;
$req->width    = 75.0;
$req->height   = 100.0;

// 注意：多页打印直接调用 tsplPdfsPrint 方法，不走 getAcsResponse
$resp = $client->tsplPdfsPrint($req);
echo $resp->toJson();
```

### 5.6 PDF 直接打印（连续纸，打印第一页）

```php
use Kuaimai\Request\Esc\EscPdfPrintRequest;

$req = new EscPdfPrintRequest();
$req->sn         = $testSn;
$req->filePath   = '/path/to/箱标.pdf';
$req->printWidth = 58.0;                 // 打印宽度（mm），默认 58
$req->endFeed    = 3;                    // 打印后走纸行数

$resp = $client->getAcsResponse($req);
echo $resp->toJson();
```

### 5.7 PDF 多页打印（连续纸，打印所有页）

```php
use Kuaimai\Request\Esc\EscPdfPrintRequest;

$req = new EscPdfPrintRequest();
$req->sn         = $testSn;
$req->filePath   = '/path/to/箱标.pdf';
$req->printWidth = 58.0;
$req->endFeed    = 3;

// 注意：多页打印直接调用 escPdfsPrint 方法，不走 getAcsResponse
$resp = $client->escPdfsPrint($req);
echo $resp->toJson();
```

### 5.8 查询设备状态（支持批量）

```php
use Kuaimai\Request\Device\QueryDeviceStatusRequest;

$req = new QueryDeviceStatusRequest();
$req->sns = json_encode(['SN001', 'SN002', 'SN003']);  // JSON 数组字符串

$resp = $client->getAcsResponse($req);
```

### 5.9 语音播报

```php
use Kuaimai\Request\Misc\BroadcastRequest;

$req = new BroadcastRequest();
$req->sn            = $testSn;
$req->volume        = 80;               // 音量 1-100
$req->volumeContent = '您有新的订单，请及时处理';

$resp = $client->getAcsResponse($req);
```

---

## 六、在自己的项目中集成 SDK

将收到的 `php-kuaimai-core` 文件夹放到你的项目旁边（同级目录），然后在你项目的 `composer.json` 中添加：

```json
{
    "require": {
        "php": ">=8.0",
        "kuaimai/php-kuaimai-core": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../php-kuaimai-core"
        }
    ]
}
```

然后执行：

```bash
composer install
```

> `url` 中的路径根据实际放置位置调整，指向 `php-kuaimai-core` 目录即可。

### 框架集成（Laravel / ThinkPHP 等）

SDK 基于 Composer 标准自动加载，可以直接在任何 PHP 框架中使用，无需额外适配：

```php
// 在 Controller 中使用
use Kuaimai\KuaimaiClient;
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;

class PrintController
{
    public function print()
    {
        $client = KuaimaiClient::createClient(
            config('kuaimai.app_id'),
            config('kuaimai.app_secret')
        );

        $req = new TsplTemplatePrintRequest();
        $req->sn              = '打印机SN';
        $req->templateId      = 1634989639;
        $req->renderDataArray = json_encode($renderDataArray);

        $resp = $client->getAcsResponse($req);
        return response()->json($resp->toArray());
    }
}
```

---

## 七、常见问题

### Q1: `composer install` 报错 "ext-gd is missing"

服务器未安装 GD 扩展，参考上方「1.4 服务器依赖安装」安装 `php-gd`。

### Q2: 模板打印出来是空白 / 文字变成方块

使用 `image=true` 时需要服务器上有中文字体，参考上方「1.4 服务器依赖安装」安装中文字体。

如果不需要本地渲染，将 `$req->image = false` 即可使用服务端渲染，不依赖本地字体。

### Q3: PDF 打印报错 "Imagick 扩展或 Ghostscript (gs) 命令行工具均未找到"

PDF 打印功能需要 Ghostscript，参考上方「1.4 服务器依赖安装」安装。

安装后验证：

```bash
gs --version    # 应输出版本号，如 10.02.1
```

### Q4: 报错 "本地网络不通，请检查网络连接"

SDK 需要访问 `http://cloud.kuaimai.com`，请确认服务器可以访问外网：

```bash
curl -v http://cloud.kuaimai.com/api/cloud/device/exist
```

### Q5: 报错 "sign 验签失败"

请检查 `appId` 和 `appSecret` 是否正确，注意前后不要有空格。

---

## 八、API 文档

完整的接口参数说明请参考：[快麦开放平台文档](https://cloudprint.kuaimai.com/#/openDev),也可查看同目录下的php-sdk.md

## 九、技术支持

如有对接问题，请联系快麦技术支持。
