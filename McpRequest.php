<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

use nova\framework\http\Request;
use nova\framework\json\Json;

/**
 * MCP 请求解析：组合当前 HTTP Request，不另起一套 Request。
 */
class McpRequest
{
    private ?array $jsonRpcData = null;

    public function __construct(private Request $request)
    {
    }

    public function getJsonRpcData(): ?array
    {
        if ($this->jsonRpcData === null) {
            // 空/非法请求体属于客户端错误，直接静默解析，由上层返回 invalidRequest
            $decoded = json_decode(Json::removeUtf8Bom(trim($this->request->raw())), true);
            $this->jsonRpcData = is_array($decoded) ? $decoded : null;
        }
        return $this->jsonRpcData;
    }

    /**
     * 是否为有效的JSON-RPC请求
     */
    public function isValidJsonRpc(): bool
    {
        $data = $this->getJsonRpcData();
        return $data &&
               isset($data['jsonrpc']) &&
               $data['jsonrpc'] === '2.0' &&
               isset($data['method']);
    }

    /**
     * 获取方法名
     */
    public function getMethod(): string
    {
        return $this->getJsonRpcData()['method'] ?? '';
    }

    /**
     * 获取参数
     */
    public function getParams(): array
    {
        $params = $this->getJsonRpcData()['params'] ?? [];
        return is_array($params) ? $params : [];
    }

    /**
     * 获取ID
     */
    public function getId(): mixed
    {
        $data = $this->getJsonRpcData();
        return $data['id'] ?? null;
    }

    public function getSessionId(): string
    {
        return $this->header('Mcp-Session-Id');
    }

    /** 客户端是否接受 POST 响应以 SSE 返回（Streamable HTTP） */
    public function acceptsSse(): bool
    {
        return str_contains($this->header('Accept'), 'text/event-stream');
    }

    /** getallheaders 保留客户端原始大小写，只能逐个比 */
    private function header(string $name): string
    {
        foreach ($this->request->getHeaders() as $k => $v) {
            if (strcasecmp((string)$k, $name) === 0 && is_string($v)) {
                return $v;
            }
        }
        return '';
    }

    /**
     * 是否为通知（无ID）
     */
    public function isNotification(): bool
    {
        $data = $this->getJsonRpcData();
        return $data && !array_key_exists('id', $data);
    }
}
