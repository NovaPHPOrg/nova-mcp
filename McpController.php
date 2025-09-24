<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

use nova\framework\core\Logger;
use nova\framework\route\Controller;
use nova\framework\http\Response;

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
    /** @var McpRequest MCP请求实例 */
    protected McpRequest $mcpRequest;

    /** @var McpServer MCP服务器注册器 */
    protected McpServer $mcpServer;

    public function __construct()
    {
        parent::__construct();
        $this->mcpRequest = new McpRequest();
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

            return McpResponse::success($id, $result);

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