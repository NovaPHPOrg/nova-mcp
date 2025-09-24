<?php

declare(strict_types=1);

namespace nova\plugin\mcp;

/**
 * MCP工具抽象
 * 
 * 简洁的工具定义和执行
 * 
 * @author Ankio
 * @version 1.0
 */
abstract class McpTool
{
    public function __construct(
        protected string $name,
        protected string $description,
        protected array $inputSchema = []
    ) {}

    /**
     * 获取工具信息
     */
    public function getInfo(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema
        ];
    }

    /**
     * 执行工具
     */
    abstract public function execute(array $arguments): array;

    /**
     * 验证参数是否符合 schema
     */
    public function validateArguments(array $arguments): void
    {
        if (empty($this->inputSchema)) {
            return; // 没有 schema 就不验证
        }

        // 检查必填参数
        if (isset($this->inputSchema['required'])) {
            $this->validateRequired($arguments, $this->inputSchema['required']);
        }

        // 验证参数类型（简化版本）
        if (isset($this->inputSchema['properties'])) {
            $this->validateTypes($arguments, $this->inputSchema['properties']);
        }
    }

    /**
     * 验证参数类型
     */
    private function validateTypes(array $arguments, array $properties): void
    {
        foreach ($arguments as $key => $value) {
            if (!isset($properties[$key])) {
                continue; // 允许额外参数
            }

            $expectedType = $properties[$key]['type'] ?? null;
            if (!$expectedType) {
                continue;
            }

            $actualType = gettype($value);
            $validTypes = [
                'string' => 'string',
                'integer' => 'integer', 
                'number' => ['integer', 'double'],
                'boolean' => 'boolean',
                'array' => 'array',
                'object' => 'array' // JSON object = PHP array
            ];

            $expected = $validTypes[$expectedType] ?? null;
            if ($expected && !in_array($actualType, (array)$expected)) {
                throw new \InvalidArgumentException("Parameter '$key' must be of type $expectedType, got $actualType");
            }
        }
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