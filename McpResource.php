<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

/**
 * MCP资源抽象
 * 
 * 简洁的资源定义和读取
 * 
 * @author Ankio
 * @version 1.0
 */
abstract class McpResource
{
    public function __construct(
        protected string $uri,
        protected string $name,
        protected ?string $description = null,
        protected ?string $mimeType = null
    ) {}

    /**
     * 获取资源信息
     */
    public function getInfo(): array
    {
        $info = [
            'uri' => $this->uri,
            'name' => $this->name
        ];

        if ($this->description) {
            $info['description'] = $this->description;
        }

        if ($this->mimeType) {
            $info['mimeType'] = $this->mimeType;
        }

        return $info;
    }

    /**
     * 读取资源内容
     */
    abstract public function read(): array;

    /**
     * 获取URI
     */
    public function getUri(): string
    {
        return $this->uri;
    }
} 