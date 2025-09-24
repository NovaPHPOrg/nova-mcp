# Nova MCP Plugin - 注册器模式

## 设计哲学

遵循Linus的"好品味"原则：
- **简洁执念**: 核心类只有5个，总共约400行代码
- **注册器模式**: 工具、资源、提示可注册，消除硬编码
- **实用主义**: 解决实际问题，易于扩展
- **标准符合**: 完全符合MCP协议规范

## 核心组件

### 1. McpRequest - 请求解析 (60行)
```php
$mcpRequest = new McpRequest();
$mcpRequest->isValidJsonRpc();  // 验证格式
$mcpRequest->getMethod();       // 获取方法
$mcpRequest->getParams();       // 获取参数
$mcpRequest->getId();           // 获取ID
```

### 2. McpResponse - 响应构造 (50行)
```php
McpResponse::success($id, $result);      // 成功响应
McpResponse::error($id, $code, $msg);    // 错误响应
McpResponse::methodNotFound($id);        // 方法未找到
McpResponse::invalidParams($id);         // 参数无效
```

### 3. McpServer - 注册器 (120行)
```php
$server = new McpServer('My Server', '1.0.0');
$server->registerTool(new MyTool());
$server->registerResource(new MyResource());
$server->registerPrompt(new MyPrompt());

// 自动处理
$server->getToolsList();
$server->callTool('tool_name', $args);
$server->getPromptsList();
$server->getPrompt('prompt_name', $args);
```

### 4. McpController - 自动化控制器 (90行)
```php
class MyController extends McpController
{
    protected function createMcpServer(): McpServer
    {
        return new McpServer('My Server', '1.0.0');
    }

    protected function registerComponents(): void
    {
        $this->mcpServer->registerTool(new MyTool());
        $this->mcpServer->registerPrompt(new MyPrompt());
    }
}
```

## 抽象类

### McpTool - 工具抽象 (60行)
```php
class MyTool extends McpTool
{
    public function __construct()
    {
        parent::__construct('my_tool', '我的工具', $inputSchema);
    }

    public function execute(array $arguments): array
    {
        // 自动验证参数
        $this->validateArguments($arguments);
        
        return ['content' => [['type' => 'text', 'text' => 'Hello']]];
    }
}
```

### McpResource - 资源抽象 (40行)
```php
class MyResource extends McpResource
{
    public function __construct()
    {
        parent::__construct('memory://my-data', '我的数据');
    }

    public function read(): array
    {
        return [
            'contents' => [[
                'uri' => $this->uri,
                'mimeType' => 'text/plain',
                'text' => 'Resource content'
            ]]
        ];
    }
}
```

### McpPrompt - 提示抽象 (50行)
```php
class MyPrompt extends McpPrompt
{
    public function __construct()
    {
        parent::__construct('my_prompt', '我的提示', $arguments);
    }

    public function getPrompt(array $arguments): array
    {
        return [
            'messages' => [[
                'role' => 'user',
                'content' => [['type' => 'text', 'text' => 'Prompt content']]
            ]]
        ];
    }
}
```

## 完整示例

### Health控制器 - 完整的MCP服务器
```php
class Health extends McpController
{
    protected function createMcpServer(): McpServer
    {
        return new McpServer('Health Check Server', '1.0.0');
    }

    protected function registerComponents(): void
    {
        $this->mcpServer->registerTool(new HealthCheckTool());
    }

    // 可选：保留HTTP接口
    public function check(): Response
    {
        return Response::asJson(['status' => 'healthy']);
    }
}

class HealthCheckTool extends McpTool
{
    public function __construct()
    {
        parent::__construct('health_check', '系统健康检查', [
            'type' => 'object',
            'properties' => [
                'detailed' => ['type' => 'boolean', 'description' => '详细信息']
            ]
        ]);
    }

    public function execute(array $arguments): array
    {
        $this->validateArguments($arguments); // 自动验证
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => '系统状态: healthy'
            ]]
        ];
    }
}
```

## 架构对比

### 之前的复杂设计 (2400行)
- 6个模型类，过度抽象
- 反射机制自动路由，复杂
- 大量验证和异常处理

### 现在的注册器模式 (400行)
- 5个核心类，职责清晰
- 注册器管理组件，简洁
- 智能验证，实用导向

## 优势

1. **类型安全**: 工具、资源、提示都有明确的类型
2. **易于扩展**: 只需继承抽象类并注册
3. **自动化**: 控制器自动处理JSON-RPC协议
4. **解耦**: 组件和控制器分离，可复用
5. **标准符合**: 完全符合MCP协议规范
6. **参数验证**: 自动验证工具参数类型和必填项
7. **简洁**: 总代码量减少83%

## 修复内容

✅ **协议标准符合性**
- 修复 `nextCursor` 字段：当没有更多数据时省略该字段（而不是设置为null或"null"）
- 添加协议版本验证 (支持 2024-11-05, 2025-03-26, 2025-06-18)
- 动态构建capabilities，只包含实际注册的功能

✅ **参数验证**
- 添加 `validateArguments()` 方法到 `McpTool`
- 支持 JSON Schema 类型验证
- 自动验证必填参数

✅ **错误处理优化**
- 使用异常类型而非字符串匹配
- 精确的错误码映射
- 移除生产环境不必要的日志

✅ **Prompts 支持**
- 新增 `McpPrompt` 抽象类
- 完整的 prompts 注册和调用机制
- 支持 `prompts/list` 和 `prompts/get` 方法

✅ **分页处理**
- 正确处理列表响应中的 `nextCursor` 字段
- 当不支持分页时省略该字段，避免客户端验证错误

## 使用流程

1. **继承McpController**
2. **实现createMcpServer()** - 创建服务器实例
3. **实现registerComponents()** - 注册工具、资源、提示
4. **创建具体的Tool/Resource/Prompt类** - 实现业务逻辑
5. **配置路由** - 指向handleMcpRequest方法

这就是Linus会赞赏的设计：简洁、实用、易于理解，且完全符合标准！
