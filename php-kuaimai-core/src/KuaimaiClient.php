<?php

namespace Kuaimai;

use Kuaimai\Bean\ResponseEnvelope;
use Kuaimai\Constants\Constants;
use Kuaimai\Exceptions\CheckParamsException;
use Kuaimai\Request\Device\AdjustDeviceDensityRequest;
use Kuaimai\Request\Device\BindDeviceRequest;
use Kuaimai\Request\Device\QueryDeviceExistRequest;
use Kuaimai\Request\Device\QueryDeviceStatusRequest;
use Kuaimai\Request\Device\UnbindDeviceRequest;
use Kuaimai\Request\Esc\EscImageRequest;
use Kuaimai\Request\Esc\EscInstructRequest;
use Kuaimai\Request\Esc\EscPdfPrintRequest;
use Kuaimai\Request\Esc\EscTemplatePrintRequest;
use Kuaimai\Request\Esc\EscXmlWriteRequest;
use Kuaimai\Request\Misc\BroadcastRequest;
use Kuaimai\Request\Misc\CainiaoBindRequest;
use Kuaimai\Request\Misc\CainiaoPrintRequest;
use Kuaimai\Request\Misc\CancelJobRequest;
use Kuaimai\Request\Misc\CombinationRequest;
use Kuaimai\Request\Misc\GetCainiaoCodeRequest;
use Kuaimai\Request\Misc\ResultRequest;
use Kuaimai\Request\Tspl\TsplImageRequest;
use Kuaimai\Request\Tspl\TsplInstructRequest;
use Kuaimai\Request\Tspl\TsplPdfPrintRequest;
use Kuaimai\Request\Tspl\TsplTemplatePrintRequest;
use Kuaimai\Request\Tspl\TsplTemplateWriteRequest;
use Kuaimai\Request\Tspl\TsplXmlWriteRequest;
use Kuaimai\Util\HexUtils;
use Kuaimai\Util\PdfUtils;
use Kuaimai\Util\RequestTool;
use Kuaimai\Util\SignTool;
use Kuaimai\Util\StringUtils;
use Kuaimai\Util\TemplateRenderer;
use Kuaimai\Util\TsplUtil;

class KuaimaiClient
{
    private static ?KuaimaiClient $instance = null;

    private string $accessKey;
    private string $secret;

    private function __construct(string $accessKey, string $secret)
    {
        $this->accessKey = $accessKey;
        $this->secret    = $secret;
    }

    public static function createClient(string $accessKey, string $secret): self
    {
        if (self::$instance === null) {
            self::$instance = new self($accessKey, $secret);
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Returns current Beijing time (Asia/Shanghai) formatted as "Y-m-d H:i:s" */
    private function now(): string
    {
        return (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s');
    }

    /**
     * Build params map, sign it, then append version for request tracing/logging.
     * $params must contain only the fields that should be included in the signature
     * (i.e. match exactly what Java puts in the HashMap before calling createSign).
     */
    private function post(string $endpoint, array $params): ResponseEnvelope
    {
        $params['sign']    = SignTool::createSign($params, $this->secret);
        $params['version'] = Constants::VERSION;
        return RequestTool::postRequest(Constants::BASE_URL . $endpoint, $params);
    }

    private function baseMap(array $extra = []): array
    {
        return array_merge(['appId' => $this->accessKey, 'timestamp' => $this->now()], $extra);
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public function getAcsResponse(object $request): ResponseEnvelope
    {
        return match (true) {
            $request instanceof QueryDeviceExistRequest    => $this->queryDeviceExist($request),
            $request instanceof QueryDeviceStatusRequest   => $this->queryDeviceStatus($request),
            $request instanceof BindDeviceRequest          => $this->bindDevice($request),
            $request instanceof UnbindDeviceRequest        => $this->unbindDevice($request),
            $request instanceof AdjustDeviceDensityRequest => $this->adjustDeviceDensity($request),
            $request instanceof EscInstructRequest         => $this->escInstruct($request),
            $request instanceof EscTemplatePrintRequest    => $this->escTemplatePrint($request),
            $request instanceof EscXmlWriteRequest         => $this->escXmlWrite($request),
            $request instanceof EscImageRequest            => $this->escImageDirectPrint($request),
            $request instanceof EscPdfPrintRequest         => $this->escPdfPrint($request),
            $request instanceof TsplInstructRequest        => $this->tsplInstruct($request),
            $request instanceof TsplTemplatePrintRequest   => $this->tsplTemplatePrint($request),
            $request instanceof TsplTemplateWriteRequest   => $this->tsplTemplateWrite($request),
            $request instanceof TsplXmlWriteRequest        => $this->tsplXmlWrite($request),
            $request instanceof TsplImageRequest           => $this->tsplImageDirectPrint($request),
            $request instanceof TsplPdfPrintRequest        => $this->tsplPdfPrint($request),
            $request instanceof ResultRequest              => $this->result($request),
            $request instanceof BroadcastRequest           => $this->broadcast($request),
            $request instanceof CancelJobRequest           => $this->cancelJob($request),
            $request instanceof CombinationRequest         => $this->combination($request),
            $request instanceof GetCainiaoCodeRequest      => $this->getCainiaoCode($request),
            $request instanceof CainiaoBindRequest         => $this->cainiaoBind($request),
            $request instanceof CainiaoPrintRequest        => $this->cainiaoPrint($request),
            default => ResponseEnvelope::error('Unknown request type: ' . get_class($request)),
        };
    }

    // -------------------------------------------------------------------------
    // Device management
    // -------------------------------------------------------------------------

    public function queryDeviceExist(QueryDeviceExistRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap(['sn' => $req->sn]);
        return $this->post('device/exist', $p);
    }

    public function queryDeviceStatus(QueryDeviceStatusRequest $req): ResponseEnvelope
    {
        $sns = $req->sns;
        $sn  = $req->sn;

        if (StringUtils::isBlank($sn) && StringUtils::isBlank($sns)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        // Java: if sns is blank, wrap single sn into JSON array and use "snsStr" key
        if (StringUtils::isBlank($sns)) {
            $sns = json_encode([$sn]);
        }
        $p = $this->baseMap(['snsStr' => $sns]);
        return $this->post('device/batchStatus', $p);
    }

    public function bindDevice(BindDeviceRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap([
            'sn'        => $req->sn,
            'deviceKey' => $req->deviceKey,
            'bindName'  => $req->bindName,
            'noteName'  => $req->noteName,
        ]);
        return $this->post('device/bindDevice', $p);
    }

    public function unbindDevice(UnbindDeviceRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        if (StringUtils::isBlank($req->deviceKey)) {
            return ResponseEnvelope::error('deviceKey入参不能为空');
        }
        $p = $this->baseMap([
            'sn'        => $req->sn,
            'deviceKey' => $req->deviceKey,
        ]);
        return $this->post('device/unbindDevice', $p);
    }

    public function adjustDeviceDensity(AdjustDeviceDensityRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        if ($req->density < 1 || $req->density > 15) {
            return ResponseEnvelope::error('density入参范围需要为1～15');
        }
        $p = $this->baseMap([
            'sn'      => $req->sn,
            'density' => $req->density,
        ]);
        return $this->post('device/adjustDeviceDensity', $p);
    }

    // -------------------------------------------------------------------------
    // ESC/POS printing
    // -------------------------------------------------------------------------

    public function escInstruct(EscInstructRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        if (StringUtils::isBlank($req->instructions)) {
            return ResponseEnvelope::error('instructions入参为空');
        }
        // Java field name is "instructionsList"
        $p = $this->baseMap([
            'sn'               => $req->sn,
            'instructionsList' => $req->instructions,
            'copies'           => $req->copies,
            'volume'           => $req->volume,
            'volumeContent'    => null,   // kept for signing parity (filtered as null)
            'volumeIndex'      => $req->volumeIndex,
        ]);
        return $this->post('print/escWrite', $p);
    }

    public function escTemplatePrint(EscTemplatePrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap([
            'sn'           => $req->sn,
            'templateId'   => $req->templateId,
            'renderDataJson' => null,        // Java puts this; null → filtered in signing
            'renderData'   => $req->renderData,
            'copies'       => $req->copies,
            'volume'       => $req->volume,
            'endFeed'      => $req->endFeed,
        ]);
        if ($req->cut !== null) {
            $p['cut'] = $req->cut;
        }
        return $this->post('print/escTemplatePrint', $p);
    }

    public function escXmlWrite(EscXmlWriteRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap([
            'sn'           => $req->sn,
            'instructions' => $req->instructions,
            'volume'       => $req->volume,
            'volumeIndex'  => 0,
        ]);
        if ($req->cut !== null) {
            $p['cut'] = $req->cut;
        }
        return $this->post('print/escXmlWrite', $p);
    }

    public function escImageDirectPrint(EscImageRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        if (StringUtils::isBlank($req->imageBase64) && !($req->bufferedImage instanceof \GdImage)) {
            return ResponseEnvelope::error('imageBase64和bufferedImage入参不能都为空');
        }

        $endFeed = $req->endFeed > 0 ? $req->endFeed : 3;

        // Resolve image
        $img = ($req->bufferedImage instanceof \GdImage)
            ? $req->bufferedImage
            : HexUtils::base64ToImage($req->imageBase64);

        // Resize image to fit printer printable width (same as Java 300KB guard)
        // ESC printers: 58mm paper → 384 dots, 80mm paper → 560 dots (8 dots/mm)
        $width = (int)$req->printWidth;
        if ($width <= 0) {
            $width = 58;
        }
        $targetW = ($width - 10) * 8; // printable area in dots
        $imgW = imagesx($img);
        if ($imgW > $targetW) {
            $imgH    = imagesy($img);
            $newH    = (int)round($imgH * $targetW / $imgW);
            $resized = imagescale($img, $targetW, $newH, IMG_BILINEAR_FIXED);
            if ($resized !== false) {
                if ($img !== $req->bufferedImage) {
                    imagedestroy($img);
                }
                $img = $resized;
            }
        }

        // ESC/POS init bytes
        $instructs = "\x1B\x40";

        // Width command (if set)
        if ($width > 0) {
            $instructs .= chr(31) . chr(27) . chr(26) . chr(1) . chr(2) . chr(4) . chr($width);
        }

        // Image → ESC/POS raster bytes (chunked + compressed, same as Java getEscImageBytes)
        $instructs .= HexUtils::getEscImageBytes($img, true);

        // Feed bytes
        $instructs .= "\x1B\x64" . chr($endFeed);

        // Send via imageWrite endpoint (same as Java)
        $tsplReq               = new TsplInstructRequest();
        $tsplReq->sn           = $req->sn;
        $tsplReq->instructs    = base64_encode($instructs);
        $tsplReq->prereq       = 'FFFF01';
        $tsplReq->extra        = '2';
        return $this->tsplImageWrite($tsplReq);
    }

    public function escPdfPrint(EscPdfPrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->filePath) || !file_exists($req->filePath)) {
            return ResponseEnvelope::error('pdf文件不存在');
        }
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        try {
            $img = PdfUtils::convertPdfToImage($req->filePath, 203);
            $escReq              = new EscImageRequest();
            $escReq->sn          = $req->sn;
            $escReq->bufferedImage = $img;
            $escReq->printWidth  = $req->printWidth;
            $escReq->endFeed     = $req->endFeed;
            return $this->escImageDirectPrint($escReq);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error('pdf转换图片失败: ' . $e->getMessage());
        }
    }

    /** ESC/POS PDF 多页打印（对应 Java escPdfsPrint） */
    public function escPdfsPrint(EscPdfPrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->filePath) || !file_exists($req->filePath)) {
            return ResponseEnvelope::error('pdf文件不存在');
        }
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        try {
            $images = PdfUtils::convertPdfsToImage($req->filePath, 203);
            $results = [];
            foreach ($images as $img) {
                $escReq              = new EscImageRequest();
                $escReq->sn          = $req->sn;
                $escReq->bufferedImage = $img;
                $escReq->printWidth  = $req->printWidth;
                $escReq->endFeed     = $req->endFeed;
                $results[] = $this->escImageDirectPrint($escReq);
            }
            $resp         = new ResponseEnvelope();
            $resp->status = true;
            $resp->data   = array_map(fn($r) => $r->toArray(), $results);
            return $resp;
        } catch (\Throwable $e) {
            return ResponseEnvelope::error('pdf转换图片失败: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // TSPL printing
    // -------------------------------------------------------------------------

    public function tsplInstruct(TsplInstructRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        if (StringUtils::isBlank($req->instructs)) {
            return ResponseEnvelope::error('指令内容为空');
        }
        $p = $this->baseMap([
            'sn'       => $req->sn,
            'instructs' => $req->instructs,
            'prereq'   => $req->prereq,
            'extra'    => $req->extra,
        ]);
        return $this->post('print/deviceWrite', $p);
    }

    /** Internal: send instruction bytes to print/imageWrite (used by image-based printing) */
    private function tsplImageWrite(TsplInstructRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        if (StringUtils::isBlank($req->instructs)) {
            return ResponseEnvelope::error('指令内容为空');
        }
        $p = $this->baseMap([
            'sn'       => $req->sn,
            'instructs' => $req->instructs,
            'prereq'   => $req->prereq,
            'extra'    => $req->extra,
        ]);
        return $this->post('print/imageWrite', $p);
    }

    public function tsplTemplatePrint(TsplTemplatePrintRequest $req): ResponseEnvelope
    {
        $sn   = $req->sn;
        $imei = $req->imei;

        if ($req->templateId === null) {
            return ResponseEnvelope::error('templateId不能为空');
        }
        // KM360C (Cainiao) path: imei set, sn not set
        if (StringUtils::isNotBlank($imei) && StringUtils::isBlank($sn)) {
            return $this->tsplImageCainiaoPrint($req);
        }
        if (StringUtils::isBlank($sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }

        // image=true: Java 走本地渲染路径（getTemplate → 本地渲染 → print/imageWrite）
        if ($req->image) {
            return $this->tsplImagePrint($req);
        }

        // image=false: 服务端渲染（print/tsplTemplatePrint）
        $p = $this->baseMap([
            'sn'         => $sn,
            'templateId' => $req->templateId,
            'printTimes' => $req->printTimes,
        ]);
        if (StringUtils::isNotBlank($req->renderDataArray)) {
            $p['renderDataArray'] = $req->renderDataArray;
        }
        if (StringUtils::isNotBlank($req->renderData)) {
            $p['renderData'] = $req->renderData;
        }
        return $this->post('print/tsplTemplatePrint', $p);
    }

    /**
     * 本地渲染路径：对应 Java tsplImagePrint()
     * 1. getTemplate 取模板 JSON
     * 2. TemplateRenderer 渲染每组数据 → GD Image
     * 3. 转 TSPL 指令字节 → print/imageWrite
     */
    private function tsplImagePrint(TsplTemplatePrintRequest $req): ResponseEnvelope
    {
        $templateResp = $this->getTemplate($req->templateId);
        if (!$templateResp) {
            return ResponseEnvelope::error('getTemplate 失败');
        }
        if (!$templateResp->status) {
            return $templateResp;
        }

        $templateData = (array)$templateResp->data;
        $renderDataResponse = $this->resolveRenderDataItems($req);
        if (!$renderDataResponse->status) {
            return $renderDataResponse;
        }
        $items = $renderDataResponse->data;

        $printTimes = $this->normalizePrintTimes($req->printTimes);
        $allBytes   = '';

        foreach ($items as $index => $renderData) {
            try {
                $img = TemplateRenderer::render($templateData, (array)$renderData);
                $this->dumpRenderedDebugImage($img, $req->templateId, $index);
            } catch (\Throwable $e) {
                return ResponseEnvelope::error('模板本地渲染失败: ' . $e->getMessage());
            }

            // 取标签尺寸（mm）
            $tagConfig = json_decode($templateData['tagConfig'] ?? '{}', true) ?: [];
            $widthMm   = (float)($tagConfig['width']  ?? 75);
            $heightMm  = (float)($tagConfig['height'] ?? 100);

            // 生成 TSPL 指令
            $chunk  = TsplUtil::crtiSize($widthMm, $heightMm);
            $chunk .= TsplUtil::crtiClear();

            foreach (HexUtils::tsplBitmapBytes($img, 0, 0, 3) as $bitmapChunk) {
                $chunk .= $bitmapChunk;
            }
            $chunk .= TsplUtil::crtiPrint(1, $printTimes);

            $allBytes .= $chunk;
        }

        // 检查大小（Java 限制 150KB）
        $encoded = base64_encode($allBytes);
        if (strlen($encoded) > 150 * 1024) {
            return ResponseEnvelope::error('渲染数据量过大,请减少渲染数量');
        }

        $tsplReq            = new TsplInstructRequest();
        $tsplReq->sn        = $req->sn;
        $tsplReq->instructs = $encoded;
        $tsplReq->prereq    = 'FFFF01';
        $tsplReq->extra     = '0';
        return $this->tsplImageWrite($tsplReq);
    }

    /**
     * KM360C 云打印机模板打印：本地渲染模板为 PNG，然后逐张调用 cainiaoPrint()
     */
    private function tsplImageCainiaoPrint(TsplTemplatePrintRequest $req): ResponseEnvelope
    {
        $templateResp = $this->getTemplate($req->templateId);
        if (!$templateResp) {
            return ResponseEnvelope::error('getTemplate 失败');
        }
        if (!$templateResp->status) {
            return $templateResp;
        }

        $templateData = (array)$templateResp->data;
        $renderDataResponse = $this->resolveRenderDataItems($req);
        if (!$renderDataResponse->status) {
            return $renderDataResponse;
        }

        $items = $renderDataResponse->data;
        $printTimes = $this->normalizePrintTimes($req->printTimes);

        foreach ($items as $index => $renderData) {
            try {
                $img = TemplateRenderer::render($templateData, (array)$renderData);
                $this->dumpRenderedDebugImage($img, $req->templateId, $index);
                $imageBase64 = HexUtils::imageToBase64($img);
            } catch (\Throwable $e) {
                return ResponseEnvelope::error('模板渲染失败: ' . $e->getMessage());
            }

            for ($copyIndex = 0; $copyIndex < $printTimes; $copyIndex++) {
                $cainiaoReq = new CainiaoPrintRequest();
                $cainiaoReq->imei = $req->imei;
                $cainiaoReq->imageBase64Data = $imageBase64;
                $response = $this->cainiaoPrint($cainiaoReq);
                if (!$response->status) {
                    return $response;
                }
            }
        }

        $response = new ResponseEnvelope();
        $response->status = true;
        $response->message = '下发成功';
        return $response;
    }

    private function dumpRenderedDebugImage(\GdImage $img, int $templateId, int $index): void
    {
        $dir = getenv('KUAIMAI_RENDER_DEBUG_DIR');
        if (StringUtils::isBlank($dir)) {
            return;
        }

        $dir = rtrim($dir, '/');
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            error_log('[KuaimaiClient] debug image directory create failed: ' . $dir);
            return;
        }

        $file = sprintf('%s/template-%d-page-%02d.png', $dir, $templateId, $index + 1);
        if (!@imagepng($img, $file)) {
            error_log('[KuaimaiClient] debug image write failed: ' . $file);
        }
    }

    private function resolveRenderDataItems(TsplTemplatePrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isNotBlank($req->renderDataArray)) {
            $items = json_decode($req->renderDataArray, true);
            if (!is_array($items)) {
                return ResponseEnvelope::error('renderDataArray JSON 格式有误');
            }
            if (count($items) < 1) {
                return ResponseEnvelope::error('渲染数据不能为空');
            }

            $response = new ResponseEnvelope();
            $response->status = true;
            $response->data = $items;
            return $response;
        }

        if (StringUtils::isNotBlank($req->renderData)) {
            $item = json_decode($req->renderData, true);
            if (!is_array($item)) {
                return ResponseEnvelope::error('renderData JSON 格式有误');
            }

            $response = new ResponseEnvelope();
            $response->status = true;
            $response->data = [$item];
            return $response;
        }

        return ResponseEnvelope::error('渲染数据不能为空');
    }

    private function normalizePrintTimes(int $printTimes): int
    {
        return $printTimes > 0 ? $printTimes : 1;
    }

    /** 对应 Java getTemplate()，从服务端获取模板 JSON */
    public function getTemplate(int $templateId): ?ResponseEnvelope
    {
        if (!$templateId) return null;
        $p = $this->baseMap(['templateId' => $templateId]);
        return $this->post('print/getTemplate', $p);
    }

    public function tsplTemplateWrite(TsplTemplateWriteRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap([
            'sn'         => $req->sn,
            'templateId' => $req->templateId,
            'printTimes' => $req->printTimes,
        ]);
        if (StringUtils::isNotBlank($req->renderData)) {
            $p['renderData'] = $req->renderData;
        }
        return $this->post('print/tsplTemplateWrite', $p);
    }

    public function tsplXmlWrite(TsplXmlWriteRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        $p = $this->baseMap([
            'sn'         => $req->sn,
            'xmlStr'     => $req->xmlStr,
            'printTimes' => $req->printTimes,
            // Java 签名时直接使用 Boolean，字符串化后是 "true"/"false"
            'image'      => $req->image,
            'jobs'       => $req->jobs,
        ]);
        return $this->post('print/tsplXmlWrite', $p);
    }

    public function tsplImageDirectPrint(TsplImageRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        if (StringUtils::isBlank($req->imageBase64) && !($req->bufferedImage instanceof \GdImage)) {
            return ResponseEnvelope::error('入参不能为空');
        }

        // Resolve image
        $img = ($req->bufferedImage instanceof \GdImage)
            ? $req->bufferedImage
            : HexUtils::base64ToImage($req->imageBase64);

        $dpi     = $req->dpi >= 300 ? 300 : 203;
        $pxPerMm = $dpi >= 300 ? 11.8 : 8.0;

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        // Determine label size in mm and target pixel dimensions — matches Java logic
        if ($req->setWidth > 0 && $req->setHeight > 0) {
            if ($dpi >= 300) {
                $targetW = (int)(floor($req->setWidth  * 11.8 / 8) * 8);
                $targetH = (int)(floor($req->setHeight * 11.8 / 8) * 8);
            } else {
                $targetW = (int)($req->setWidth  * 8);
                $targetH = (int)($req->setHeight * 8);
            }
            $labelW = (int)$req->setWidth;
            $labelH = (int)$req->setHeight;
        } else {
            if ($dpi >= 300) {
                $labelW  = (int)ceil($srcW / 11.8);
                $labelH  = (int)ceil($srcH / 11.8);
                $targetW = $srcW;
                $targetH = $srcH;
            } else {
                $labelW  = (int)ceil($srcW / 8);
                $labelH  = (int)ceil($srcH / 8);
                $targetW = $srcW;
                $targetH = $srcH;
            }
        }

        if ($targetW !== $srcW || $targetH !== $srcH) {
            $img = HexUtils::resize($img, $targetW, $targetH);
        }

        // Build TSPL instruction bytes
        $instruct = '';
        $instruct .= TsplUtil::crtiSize($labelW, $labelH);
        $instruct .= TsplUtil::crtiClear();

        $bitmapChunks = HexUtils::tsplBitmapBytes($img, 0, 0, 3);
        foreach ($bitmapChunks as $chunk) {
            $instruct .= $chunk;
        }
        $instruct .= TsplUtil::crtiPrint(1, $req->printTimes);

        $tsplReq            = new TsplInstructRequest();
        $tsplReq->sn        = $req->sn;
        $tsplReq->instructs = base64_encode($instruct);
        $tsplReq->prereq    = 'FFFF01';
        $tsplReq->extra     = '0';
        return $this->tsplImageWrite($tsplReq);
    }

    public function tsplPdfPrint(TsplPdfPrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->filePath) || !file_exists($req->filePath)) {
            return ResponseEnvelope::error('pdf文件不存在');
        }
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        $dpi = $req->dpi >= 300 ? 300 : 203;
        try {
            $img = PdfUtils::convertPdfToImage($req->filePath, $dpi);
            $tsplReq              = new TsplImageRequest();
            $tsplReq->sn          = $req->sn;
            $tsplReq->dpi         = $dpi;
            $tsplReq->bufferedImage = $img;
            $tsplReq->setWidth    = $req->width;
            $tsplReq->setHeight   = $req->height;
            return $this->tsplImageDirectPrint($tsplReq);
        } catch (\Throwable $e) {
            return ResponseEnvelope::error('pdf转换图片失败: ' . $e->getMessage());
        }
    }

    /** TSPL PDF 多页打印（对应 Java tsplPdfsPrint） */
    public function tsplPdfsPrint(TsplPdfPrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->filePath) || !file_exists($req->filePath)) {
            return ResponseEnvelope::error('pdf文件不存在');
        }
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        $dpi = $req->dpi >= 300 ? 300 : 203;
        try {
            $images = PdfUtils::convertPdfsToImage($req->filePath, $dpi);
            $results = [];
            foreach ($images as $img) {
                $tsplReq              = new TsplImageRequest();
                $tsplReq->sn          = $req->sn;
                $tsplReq->dpi         = $dpi;
                $tsplReq->bufferedImage = $img;
                $tsplReq->setWidth    = $req->width;
                $tsplReq->setHeight   = $req->height;
                $results[] = $this->tsplImageDirectPrint($tsplReq);
            }
            $resp         = new ResponseEnvelope();
            $resp->status = true;
            $resp->data   = array_map(fn($r) => $r->toArray(), $results);
            return $resp;
        } catch (\Throwable $e) {
            return ResponseEnvelope::error('pdf转换图片失败: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    public function result(ResultRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }

        $jobIds = $req->jobIds;
        if (is_string($jobIds)) {
            $decoded = json_decode($jobIds, true);
            if (!is_array($decoded)) {
                return ResponseEnvelope::error('jobIds 需要为数组或 JSON 数组字符串');
            }
            $jobIds = $decoded;
        }
        if (!is_array($jobIds) || count($jobIds) < 1) {
            return ResponseEnvelope::error('jobId为空');
        }

        $p = $this->baseMap([
            'sn'       => $req->sn,
            'jobIds'   => $jobIds,
            'jobIdStr' => $req->jobIdStr,
        ]);
        return $this->post('print/result', $p);
    }

    public function broadcast(BroadcastRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        if (StringUtils::isBlank($req->volumeContent)) {
            return ResponseEnvelope::error('volumeContent入参不能为空');
        }
        $p = $this->baseMap([
            'sn'            => $req->sn,
            'volume'        => $req->volume,
            'volumeContent' => $req->volumeContent,
        ]);
        return $this->post('device/broadcast', $p);
    }

    public function cancelJob(CancelJobRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参不能为空');
        }
        $p = $this->baseMap(['sn' => $req->sn]);
        return $this->post('print/cancelJob', $p);
    }

    public function combination(CombinationRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->sn)) {
            return ResponseEnvelope::error('sn入参为空');
        }
        // Java: combination delegates to tsplInstruct with default prereq/extra
        $extra  = StringUtils::isBlank($req->extra)  ? '2'      : $req->extra;
        $prereq = StringUtils::isBlank($req->prereq) ? 'FFFF01' : $req->prereq;

        $tsplReq           = new TsplInstructRequest();
        $tsplReq->sn       = $req->sn;
        $tsplReq->instructs = $req->instructs;
        $tsplReq->prereq   = $prereq;
        $tsplReq->extra    = $extra;
        return $this->tsplInstruct($tsplReq);
    }

    public function getCainiaoCode(GetCainiaoCodeRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->imei)) {
            return ResponseEnvelope::error('imei入参为空');
        }
        $p = $this->baseMap(['imei' => $req->imei]);
        return $this->post('print/getCainiaoCode', $p);
    }

    public function cainiaoBind(CainiaoBindRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->code) || StringUtils::isBlank($req->imei)) {
            return ResponseEnvelope::error('code或imei入参为空');
        }
        $p = $this->baseMap([
            'imei' => $req->imei,
            'code' => $req->code,
        ]);
        return $this->post('print/cainiaoBind', $p);
    }

    public function cainiaoPrint(CainiaoPrintRequest $req): ResponseEnvelope
    {
        if (StringUtils::isBlank($req->imei) || StringUtils::isBlank($req->imageBase64Data)) {
            return ResponseEnvelope::error('imei或imageBase64Data入参为空');
        }

        // Java: converts base64 image → BMP bytes → gzip → base64
        $img      = HexUtils::base64ToImage($req->imageBase64Data ?? '');
        $bmpBytes = $this->imageToBmpBytes($img);
        $gzipped  = gzencode($bmpBytes, 9);
        $encoded  = base64_encode($gzipped);

        $p = $this->baseMap([
            'imei'      => $req->imei,
            // Java request object uses imageBase64Data as input, but the HTTP field is printData.
            'printData' => $encoded,
        ]);
        return $this->post('print/cainiaoPrint', $p);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function imageToBmpBytes(\GdImage $img): string
    {
        ob_start();
        imagebmp($img);
        return ob_get_clean();
    }
}
