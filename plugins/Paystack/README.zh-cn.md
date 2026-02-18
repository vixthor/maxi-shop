# Paystack 支付插件

一个完整的 Paystack 支付网关集成，为 InnoShop 电商平台服务。

## 功能特性

- 通过 Paystack 进行安全支付处理
- 支持多种货币
- 支付确认的 Webhook 集成
- 移动支付支持
- 测试和实时模式支持
- 完整的订单跟踪和支付历史

## 安装

1. 将此插件放在 `plugins/Paystack` 目录中
2. 运行 `composer install` 安装依赖：
   ```
   cd plugins/Paystack
   composer install
   ```
3. 在 InnoShop 管理面板中启用该插件
4. 在插件设置中配置您的 Paystack API 密钥

## 配置

配置 Paystack 支付插件：

1. 进入管理面板 > 支付 > Paystack
2. 输入您的 Paystack API 凭证：
   - **公开密钥**: 在您的 Paystack 仪表板中找到
   - **秘密密钥**: 在您的 Paystack 仪表板中找到
   - **Webhook 密钥**: 可选，用于验证传入的 webhooks
   - **测试模式**: 在测试和实时模式之间切换

## 获取 Paystack API 密钥

1. 在 [paystack.com](https://paystack.com) 创建 Paystack 账户
2. 导航到 设置 > API 密钥和 Webhooks
3. 复制您的公开密钥和秘密密钥
4. 对于 webhooks，导航到 设置 > Webhooks 并设置 URL

## Webhook 设置

启用自动支付确认：

1. 获取您的 webhook URL（使用以下之一）：
   - **测试回调 URL**: `https://yourdomain.com/webhook/paystack`
   - **测试 Webhook URL**: `https://yourdomain.com/paystack/webhook`

2. 进入 Paystack 仪表板：
   - 导航到 **Settings > API Keys & Webhooks**
   - 向下滚动到 **Test Webhooks** 部分
   - 在 **Test Callback URL** 字段中输入您的 webhook URL
   - （可选）复制 webhook 密钥并添加到插件设置

3. 订阅事件：
   - 在 Paystack 仪表板中选择要监听的事件
   - 推荐：`charge.success` 事件

4. 将 webhook 密钥添加到插件设置：
   - 管理面板 > 支付 > Paystack
   - 粘贴 webhook 密钥（如果使用签名验证）

## API 端点

- `POST /paystack/initialize` - 启动支付交易
- `POST /paystack/verify` - 验证支付完成
- `POST /webhook/paystack` - **Webhook 端点**（在 Paystack 中用于"测试回调 URL"）
- `POST /paystack/webhook` - 替代 webhook 端点

## 系统要求

- PHP 7.4 或更高版本
- Laravel 9.0 或更高版本
- Paystack PHP SDK (^2.3)
- InnoShop 核心

## 支持

如有关于此插件的问题或疑问，请联系：
- 邮箱: team@innoshop.com
- 网站: https://www.innoshop.com

## 许可证

开放软件许可证 (OSL 3.0)
