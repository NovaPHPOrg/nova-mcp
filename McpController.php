<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

use nova\framework\cache\Cache;
use nova\framework\http\Response;
use nova\framework\route\Controller;

/**
 * MCP控制器基类
 *
 * 使用注册器模式管理工具和资源，自动化处理请求
 *
 * @author Ankio
 * @version 1.0
 */
abstract class McpController extends Controller
{
    private const int SESSION_TTL = 7 * 86400;

    /** @var McpRequest MCP请求实例 */
    protected McpRequest $mcpRequest;

    /** @var McpServer MCP服务器注册器 */
    protected McpServer $mcpServer;

    public function __construct()
    {
        parent::__construct();
        $this->mcpRequest = new McpRequest($this->request);
        $this->mcpServer = $this->createMcpServer();
        $this->registerComponents();
    }

    /**
     * 创建MCP服务器实例（子类实现）
     */
    abstract protected function createMcpServer(): McpServer;

    /**
     * 注册组件（子类实现）
     */
    abstract protected function registerComponents(): void;

    /**
     * 处理MCP请求的主入口
     */
    public function handleMcpRequest(): Response
    {
        try {
            if (!$this->mcpRequest->isValidJsonRpc()) {
                return McpResponse::invalidRequest($this->mcpRequest->getId());
            }

            $method = $this->mcpRequest->getMethod();
            $params = $this->mcpRequest->getParams();
            $id = $this->mcpRequest->getId();

            // 通知不需要响应
            if ($this->mcpRequest->isNotification()) {
                $this->handleNotification($method, $params);
                return Response::asNone();
            }

            // 处理请求
            return $this->handleRequest($method, $params, $id);

        } catch (\InvalidArgumentException $e) {
            return McpResponse::internalError($this->mcpRequest->getId(), $e->getMessage());
        }
    }

    /**
     * 处理JSON-RPC请求
     */
    protected function handleRequest(string $method, array $params, mixed $id): Response
    {
        try {
            $result = match ($method) {
                'initialize' => $this->mcpServer->getInitializeResponse($params['protocolVersion'] ?? '2024-11-05'),
                'resources/list' => $this->mcpServer->getResourcesList(),
                'resources/read' => $this->mcpServer->readResource($params['uri'] ?? ''),
                'tools/list' => $this->mcpServer->getToolsList(),
                'tools/call' => $this->mcpServer->callTool($params['name'] ?? '', $params['arguments'] ?? []),
                'prompts/list' => $this->mcpServer->getPromptsList(),
                'prompts/get' => $this->mcpServer->getPrompt($params['name'] ?? '', $params['arguments'] ?? []),
                default => throw new \BadMethodCallException("Method not found: $method")
            };

            return $this->reply($method, ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);

        } catch (\BadMethodCallException $e) {
            return McpResponse::methodNotFound($id);
        } catch (\InvalidArgumentException $e) {
            return McpResponse::invalidParams($id);
        } catch (\RuntimeException $e) {
            return McpResponse::error($id, -32000, $e->getMessage());
        } catch (\Throwable $e) {
            return McpResponse::internalError($id, $e->getMessage());
        }
    }

    /**
     * tools/list_changed：按会话记住客户端最后一次看到的工具面指纹，
     * 指纹变了就在本次响应前插一条通知（POST 响应走 SSE，不需要长连接）。
     * 不带 Mcp-Session-Id 或不接受 SSE 的客户端照旧拿纯 JSON。
     */
    private function reply(string $method, array $message): Response
    {
        $isInit = $method === 'initialize';
        $sid = $isInit ? bin2hex(random_bytes(16)) : $this->mcpRequest->getSessionId();
        if ($sid === '') {
            return Response::asJson($message);
        }

        $cache = new Cache();
        $key = 'mcp_tools_seen:' . $sid;
        $hash = $this->mcpServer->toolsHash();
        $seen = $cache->get($key);
        if ($isInit || $method === 'tools/list' || $seen === null) {
            $cache->set($key, $hash, self::SESSION_TTL);
            $seen = $hash;
        }

        $header = $isInit ? ['Mcp-Session-Id' => $sid] : [];
        if ($seen === $hash || !$this->mcpRequest->acceptsSse()) {
            return Response::asJson($message, 200, $header);
        }

        $notice = ['jsonrpc' => '2.0', 'method' => 'notifications/tools/list_changed'];
        $body = '';
        foreach ([$notice, $message] as $m) {
            $body .= "event: message\ndata: " . json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        return Response::asText($body, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * 处理通知
     */
    protected function handleNotification(string $method, array $params): void
    {
        // 通知处理可以在子类中重写
        match ($method) {
            'notifications/initialized' => $this->onInitialized($params),
            'notifications/cancelled' => $this->onCancelled($params),
            default => null
        };
    }

    /**
     * 初始化完成通知
     */
    protected function onInitialized(array $params): void
    {
        // 子类可以重写
    }

    /**
     * 取消操作通知
     */
    protected function onCancelled(array $params): void
    {
        // 子类可以重写
    }
}
