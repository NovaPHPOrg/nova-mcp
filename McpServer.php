<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

/**
 * MCP服务器注册器
 * 
 * 管理工具、资源、提示的注册和调用
 * 
 * @author Ankio
 * @version 1.0
 */
class McpServer
{
    /** @var array<string, McpTool> 注册的工具 */
    private array $tools = [];

    /** @var array<string, McpResource> 注册的资源 */
    private array $resources = [];

    /** @var array<string, McpPrompt> 注册的提示 */
    private array $prompts = [];

    /** @var array 服务器信息 */
    private array $serverInfo;

    /** @var array 服务器能力 */
    private array $capabilities;

    public function __construct(string $name, string $version, string $description = '')
    {
        $this->serverInfo = [
            'name' => $name,
            'version' => $version,
            'description' => $description
        ];

        // 只声明实际支持的能力
        $this->capabilities = [
            'resources' => (object)[],
            'tools' => (object)[],
            'prompts' => (object)[]
        ];
    }

    /**
     * 注册工具
     */
    public function registerTool(McpTool $tool): self
    {
        $this->tools[$tool->getInfo()['name']] = $tool;
        $this->capabilities['tools'] = (object)['listChanged' => true];
        return $this;
    }

    /**
     * 注册资源
     */
    public function registerResource(McpResource $resource): self
    {
        $this->resources[$resource->getUri()] = $resource;
        $this->capabilities['resources'] = (object)['subscribe' => true, 'listChanged' => true];
        return $this;
    }

    /**
     * 注册提示
     */
    public function registerPrompt(McpPrompt $prompt): self
    {
        $this->prompts[$prompt->getName()] = $prompt;
        $this->capabilities['prompts'] = (object)['listChanged' => true];
        return $this;
    }

    /**
     * 获取初始化响应
     */
    public function getInitializeResponse(string $protocolVersion): array
    {
        
        // 动态构建capabilities，只包含实际注册的功能
        $capabilities = [];
        
        if (!empty($this->tools)) {
            $capabilities['tools'] = (object)['listChanged' => true];
        }
        
        if (!empty($this->resources)) {
            $capabilities['resources'] = (object)['subscribe' => true, 'listChanged' => true];
        }
        
        if (!empty($this->prompts)) {
            $capabilities['prompts'] = (object)['listChanged' => true];
        }

        return [
            'protocolVersion' => $protocolVersion,
            'serverInfo' => $this->serverInfo,
            'capabilities' => (object)$capabilities
        ];
    }

    /**
     * 获取工具列表
     */
    public function getToolsList(): array
    {
        $tools = [];
        foreach ($this->tools as $tool) {
            $tools[] = $tool->getInfo();
        }

        return [
            'tools' => $tools
            // 省略 nextCursor 字段，因为我们不支持分页
        ];
    }

    /**
     * 调用工具
     */
    public function callTool(string $name, array $arguments): array
    {
        if (!isset($this->tools[$name])) {
            throw new \RuntimeException("Tool not found: $name");
        }

        $tool = $this->tools[$name];
        
        // 让工具自己验证参数
        $tool->validateArguments($arguments);

        return $tool->execute($arguments);
    }

    /**
     * 获取资源列表
     */
    public function getResourcesList(): array
    {
        $resources = [];
        foreach ($this->resources as $resource) {
            $resources[] = $resource->getInfo();
        }

        return [
            'resources' => $resources
            // 省略 nextCursor 字段，因为我们不支持分页
        ];
    }

    /**
     * 读取资源
     */
    public function readResource(string $uri): array
    {
        if (!isset($this->resources[$uri])) {
            throw new \InvalidArgumentException("Resource not found: $uri");
        }

        return $this->resources[$uri]->read();
    }

    /**
     * 获取提示列表
     */
    public function getPromptsList(): array
    {
        $prompts = [];
        foreach ($this->prompts as $prompt) {
            $prompts[] = $prompt->getInfo();
        }

        return [
            'prompts' => $prompts
            // 省略 nextCursor 字段，因为我们不支持分页
        ];
    }

    /**
     * 获取提示内容
     */
    public function getPrompt(string $name, array $arguments): array
    {
        if (!isset($this->prompts[$name])) {
            throw new \InvalidArgumentException("Prompt not found: $name");
        }

        return $this->prompts[$name]->getPrompt($arguments);
    }

    /**
     * 检查是否有工具
     */
    public function hasTools(): bool
    {
        return !empty($this->tools);
    }

    /**
     * 检查是否有资源
     */
    public function hasResources(): bool
    {
        return !empty($this->resources);
    }

    /**
     * 检查是否有提示
     */
    public function hasPrompts(): bool
    {
        return !empty($this->prompts);
    }
} 