<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

use nova\framework\core\Logger;
use nova\framework\http\Response;

/**
 * MCP响应处理类
 * 
 * 简洁的JSON-RPC 2.0响应构造
 * 
 * @author Ankio
 * @version 1.0
 */
class McpResponse
{
    /**
     * 创建成功响应
     */
    public static function success(mixed $id, mixed $result): Response
    {
        $data = [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
        
        return Response::asJson($data);
    }

    /**
     * 创建错误响应
     */
    public static function error(mixed $id, int $code, string $message, mixed $data = null): Response
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }
        
        return Response::asJson([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error
        ]);
    }

    /**
     * 方法未找到错误
     */
    public static function methodNotFound(mixed $id): Response
    {
        return self::error($id, -32601, 'Method not found');
    }

    /**
     * 参数无效错误
     */
    public static function invalidParams(mixed $id): Response
    {
        return self::error($id, -32602, 'Invalid params');
    }

    /**
     * 内部错误
     */
    public static function internalError(mixed $id, string $message = 'Internal error'): Response
    {
        return self::error($id, -32603, $message);
    }

    /**
     * 解析错误
     */
    public static function parseError(): Response
    {
        return self::error(null, -32700, 'Parse error');
    }

    /**
     * 无效请求错误
     */
    public static function invalidRequest(mixed $id = null): Response
    {
        return self::error($id, -32600, 'Invalid Request');
    }
}