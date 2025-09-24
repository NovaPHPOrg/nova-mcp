<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

/**
 * MCP提示抽象
 * 
 * 简洁的提示定义和获取
 * 
 * @author Ankio
 * @version 1.0
 */
abstract class McpPrompt
{
    public function __construct(
        protected string $name,
        protected string $description,
        protected array $arguments = []
    ) {}

    /**
     * 获取提示信息
     */
    public function getInfo(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'arguments' => $this->arguments
        ];
    }

    /**
     * 获取提示内容
     */
    abstract public function getPrompt(array $arguments): array;

    /**
     * 获取名称
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 验证参数
     */
    protected function validateRequired(array $arguments, array $required): void
    {
        foreach ($required as $param) {
            if (!isset($arguments[$param])) {
                throw new \InvalidArgumentException("Missing required parameter: $param");
            }
        }
    }
} 