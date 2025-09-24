<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

use nova\framework\http\Request;
use nova\framework\json\Json;
use nova\framework\json\JsonDecodeException;

/**
 * MCP请求处理类
 * 
 * 简洁的JSON-RPC 2.0请求解析
 * 
 * @author Ankio
 * @version 1.0
 */
class McpRequest extends Request
{
    private ?array $jsonRpcData = null;

    /**
     * 获取JSON-RPC数据
     */
    public function getJsonRpcData(): ?array
    {
        if ($this->jsonRpcData === null) {
            try {
                $this->jsonRpcData = Json::decode($this->raw(),true);
            } catch (JsonDecodeException $e) {
                $this->jsonRpcData = null;
            }
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
        return $this->getJsonRpcData()['params'] ?? [];
    }

    /**
     * 获取ID
     */
    public function getId(): mixed
    {
        $data = $this->getJsonRpcData();
        return $data['id'] ?? null;
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