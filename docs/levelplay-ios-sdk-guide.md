# Unity LevelPlay iOS SDK 对接完整指南

> 基于 Unity 官方文档整理 | SDK 版本：9.4.0+ | iOS 12+ | Xcode 15.4+
>
> 适用范围：激励视频（Rewarded Video）、插屏广告（Interstitial）、横幅广告（Banner / MREC）

---

## 目录

1. [环境要求](#1-环境要求)
2. [安装 SDK](#2-安装-sdk)
3. [配置 Info.plist](#3-配置-infoplist)
4. [初始化 SDK](#4-初始化-sdk)
5. [设置用户标识](#5-设置用户标识)
6. [激励视频广告（Rewarded Video）](#6-激励视频广告rewarded-video)
7. [插屏广告（Interstitial）](#7-插屏广告interstitial)
8. [横幅广告（Banner / MREC）](#8-横幅广告banner--mrec)
9. [隐私法规合规（GDPR / CCPA / COPPA）](#9-隐私法规合规gdpr--ccpa--coppa)
10. [聚合广告网络（Mediation Networks）](#10-聚合广告网络mediation-networks)
11. [集成测试（Test Suite）](#11-集成测试test-suite)
12. [服务端回调验证（S2S）](#12-服务端回调验证s2s)
13. [常见集成顺序问题](#13-常见集成顺序问题)
14. [Demo 应用](#14-demo-应用)

---

## 1. 环境要求

| 项目         | 要求       |
| ------------ | ---------- |
| iOS 最低版本 | 12.0       |
| Xcode        | 15.4+      |
| Swift        | 5.0+       |
| CocoaPods    | 推荐最新版 |

---

## 2. 安装 SDK

### 方式一：CocoaPods（推荐）

在 `Podfile` 中添加：

```ruby
pod 'IronSourceSDK', '9.4.0.0'
```

然后运行：

```bash
pod install
```

> 注意：如果项目没有使用 Swift，需要在 **Build Settings → Linking → Runpath Search Paths** 中添加 `/usr/lib/swift`（**必须放在列表第一行**）。

### 方式二：手动安装

1. 下载 [IronSource9.4.0.zip](https://github.com/ironsource-mobile/iOS-sdk/raw/master/9.4.0/IronSource9.4.0.zip)
2. 解压后将 `IronSource.framework` 拖入 Xcode 项目
3. **Build Settings → Other Linker Flags** 添加 `-ObjC`
4. **Linked Libraries** 添加：
   - `libz.tbd`
   - `libsqlite3.0.tbd`
5. **Linked Frameworks** 添加：
   - `JavaScriptCore.framework`
   - `WebKit.framework`
   - `AdSupport.framework`
   - `SystemConfiguration.framework`

---

## 3. 配置 Info.plist

### 3.1 SKAdNetwork 标识符

```xml
<key>SKAdNetworkItems</key>
<array>
    <dict>
        <key>SKAdNetworkIdentifier</key>
        <string>su67r6k2v3.skadnetwork</string>
    </dict>
</array>
```

> 每个聚合的广告网络都有自己独立的 SKAdNetwork ID，需要根据实际接入的网络添加全部 ID。详见官方 [SKAdNetwork ID 管理文档](https://docs.unity.com/en-us/grow/levelplay/sdk/ios/skadnetwork-id-manager)。

### 3.2 Universal SKAN 上报端点

```xml
<key>NSAdvertisingAttributionReportEndpoint</key>
<string>https://postbacks-is.com/</string>
```

### 3.3 App Transport Security（ATS）

```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

> 警告：不要在 ATS 中额外添加其他例外配置，避免冲突。

### 3.4 ATT 授权弹窗文案（iOS 14.5+）

```xml
<key>NSUserTrackingUsageDescription</key>
<string>您的广告标识符将用于提供个性化广告体验</string>
```

---

## 4. 初始化 SDK

### 4.1 导入头文件

**Objective-C：**

```objc
#import "IronSource/IronSource.h"
```

**Swift：**

```swift
// 无需显式 import，桥接头文件已配置
```

### 4.2 初始化代码

> 初始化必须在**所有广告操作之前**完成。建议在 `AppDelegate` 的 `application(_:didFinishLaunchingWithOptions:)` 中调用。
>
> 这里的“广告操作”指创建广告对象、设置广告代理、加载广告、展示广告、读取广告奖励等 SDK 广告 API。业务侧的订单创建、后端请求不属于 LevelPlay SDK 初始化参数，不需要阻塞初始化。

**Objective-C：**

```objc
// 1. 构建初始化请求
LPMInitRequestBuilder *requestBuilder = [[LPMInitRequestBuilder alloc] initWithAppKey:@"你的AppKey"];
[requestBuilder withUserId:@"用户ID"];  // 可选，用于服务端回调

// 2. 构建请求
LPMInitRequest *initRequest = [requestBuilder build];

// 3. 执行初始化
[LevelPlay initWithRequest:initRequest completion:^(LPMConfiguration * _Nullable config, NSError * _Nullable error) {
    if (error) {
        // 初始化失败，可以稍后重试（例如网络恢复后）
        NSLog(@"LevelPlay 初始化失败: %@", error.localizedDescription);
    } else {
        // 初始化成功，可以开始创建广告对象和加载广告
        NSLog(@"LevelPlay 初始化成功");
    }
}];
```

**Swift：**

```swift
// 1. 构建初始化请求
let requestBuilder = LPMInitRequestBuilder(appKey: "你的AppKey")
    .withUserId("用户ID")  // 可选，用于服务端回调

// 2. 构建请求
let initRequest = requestBuilder.build()

// 3. 执行初始化
LevelPlay.initWith(initRequest) { config, error in
    if let error = error {
        // 初始化失败，可以稍后重试
        print("LevelPlay 初始化失败: \(error.localizedDescription)")
    } else {
        // 初始化成功，可以开始创建广告对象和加载广告
        print("LevelPlay 初始化成功")
    }
}
```

### 4.3 关键参数说明

| 参数     | 类型             | 必填 | 说明                                                                       |
| -------- | ---------------- | ---- | -------------------------------------------------------------------------- |
| `appKey` | String           | ✅   | 在 [IronSource 后台](https://platform.ironsrc.com/partners/dashboard) 获取 |
| `userId` | String           | 否   | 应用的用户标识，用于服务端到服务端（S2S）回调验证                          |
| `config` | LPMConfiguration | 返回 | 初始化成功返回的配置信息                                                   |
| `error`  | NSError          | 返回 | 初始化失败时返回错误信息                                                   |

#### 4.3.1 哪些参数必须初始化时确定

初始化阶段只需要放 SDK 启动所必需的稳定参数：

| 参数 | 是否必须初始化时提供 | 是否可后续动态变更 | 说明 |
| ---- | -------------------- | ------------------ | ---- |
| `appKey` | 是 | 否 | SDK 初始化必填，通常写在应用配置中 |
| `userId` / `withUserId` | 否 | 是 | 如果初始化时已经知道用户 ID，可以传入；如果登录态或用户切换发生在后面，可用 `setDynamicUserId` 更新 |
| `adUnitId` | 否 | 创建广告对象时确定 | 不属于 SDK 初始化参数；初始化成功后创建 `LPMRewardedAd` / `LPMInterstitialAd` / `LPMBannerAdView` 时传入 |
| `placementName` | 否 | 每次展示时传入 | 不属于 SDK 初始化参数；调用 `showAd(viewController:placementName:)` 时传入 |
| `customParameters` | 否 | 是 | 不属于初始化请求；需要在展示激励广告前通过 `setRewardedVideoServerParameters` 设置 |
| `order_id` | 否 | 是 | 业务订单号，通常来自后端；不要为了等订单号阻塞 SDK 初始化 |

推荐流程：

1. App 启动后尽早用 `appKey` 初始化 LevelPlay。
2. 用户登录后，如果需要 S2S 用户标识，设置 `setDynamicUserId`。
3. 初始化成功后，用后台配置的 `adUnitId` 创建广告对象。
4. 设置广告代理并加载广告。
5. 用户点击领取/观看时，先请求后端创建业务订单，拿到 `order_id`。
6. 调用 `setRewardedVideoServerParameters(["order_id": orderId])`。
7. 立即调用 `showAd` 展示广告，可按需传入 `placementName`。

#### 4.3.2 参数设置位置速查

| 参数 | 设置位置 | 示例 |
| ---- | -------- | ---- |
| `appKey` | SDK 初始化 | `LPMInitRequestBuilder(appKey: "appKey")` |
| `userId` | 初始化或登录后 | `.withUserId("user_123")` / `LevelPlay.setDynamicUserId("user_123")` |
| `adUnitId` | 创建广告对象 | `LPMRewardedAd(adUnitId: "rewarded_ad_unit")` |
| `placementName` | 展示广告或获取奖励配置 | `showAd(viewController: self, placementName: "daily_bonus")` |
| `customParameters` | 展示激励广告前 | `IronSource.setRewardedVideoServerParameters(["order_id": orderId])` |
| `order_id` | 后端创建业务订单后 | 放入 `customParameters`，S2S 回调时从 `customParameters` 取回 |

### 4.4 初始化结果处理

- **成功**：`error == nil`，`config` 有效，可以开始加载广告
- **失败**：`error != nil`，一般是因为网络问题，SDK 不会自动重试，需要开发者自行处理（比如网络恢复后重新调用）

---

## 5. 设置用户标识

### 5.1 静态用户 ID（推荐用于 S2S 回调）

在初始化时通过 `withUserId` 传入，也可以之后动态修改：

**Objective-C：**

```objc
[LevelPlay setDynamicUserId:@"新的用户ID"];
```

**Swift：**

```swift
LevelPlay.setDynamicUserId("新的用户ID")
```

**约束：** 必须是 1-64 位字母数字字符组成的字符串，可包含字母、数字及 `-` `_` `@` `.`。

### 5.2 激励视频回调流程

```
1. 你在初始化时设置了 withUserId("user_123")
2. 用户在 iOS 端看完激励视频
3. IronSource 服务器向你配置的回调 URL 发送 POST 请求
4. 请求中包含 userId=user_123，你可以据此给 user_123 发放奖励
```

### 5.3 传递自定义参数到服务端回调（customParameters）

服务端回调中有一个 `customParameters` 字段，用于传递业务自定义数据（比如：道具 ID、关卡号、订单号等）。iOS 端通过 **IronSource SDK 全局方法** 设置。

#### 5.3.1 设置自定义参数

使用 `IronSource` 类的 `setRewardedVideoServerParameters` 方法，传入一个 `[String: String]` 字典。**需要在调用 `showAd` 之前设置**，可以每次展示广告前动态更改。

如果 `order_id` 需要从后端获取，正确做法是：先完成 SDK 初始化和广告加载；用户准备展示广告时，请求后端创建订单；拿到订单号后设置 `customParameters`；然后再调用 `showAd`。不需要、也不应该为了订单号延迟 LevelPlay 初始化。

**Objective-C：**

```objc
// 设置自定义参数（字典）
NSDictionary *params = @{
    @"item_id": @"sword_001",
    @"level": @"5",
    @"order_id": @"ORD-20260429-001"
};
[IronSource setRewardedVideoServerParameters:params];

// 然后展示广告
[self.rewardedAd showAdWithViewController:self placementName:@"main_menu"];
```

**Swift：**

```swift
// 设置自定义参数（字典）
let params: [String: String] = [
    "item_id": "sword_001",
    "level": "5",
    "order_id": "ORD-20260429-001"
]
IronSource.setRewardedVideoServerParameters(params)

// 然后展示广告
self.rewardedAd.showAd(viewController: self, placementName: "main_menu")
```

如果上一轮展示设置过参数，本轮要换成新的订单号，建议先清空旧参数再设置新参数：

**Objective-C：**

```objc
[IronSource clearRewardedVideoServerParameters];
[IronSource setRewardedVideoServerParameters:@{
    @"order_id": orderId
}];
```

**Swift：**

```swift
IronSource.clearRewardedVideoServerParameters()
IronSource.setRewardedVideoServerParameters([
    "order_id": orderId
])
```

#### 5.3.2 在回调中如何接收

服务端收到回调时，`customParameters` 字段的值就是 **URL 编码的查询字符串**：

```
customParameters = "item_id%3Dsword_001%26level%3D5%26order_id%3DORD-20260429-001"
```

**PHP 解析方式：**

```php
// 方式一：parse_str 自动解析
$custom = [];
parse_str(rawurldecode($params['customParameters']), $custom);
// $custom = ['item_id' => 'sword_001', 'level' => '5', 'order_id' => 'ORD-20260429-001']

// 方式二：通过 $_GET 获取（如果是 GET 回调）
// GET 回调中 customParameters 的 key=value 会直接展开到 URL 参数中
```

> **注意**：如果回调方式配置为 **GET**，自定义参数的 key=value 会直接展开为 URL 查询参数。如果配置为 **POST**，则作为 `customParameters` 一个独立字段。

#### 5.3.3 使用场景示例

| 场景         | 传入参数                 | 后端用途                 |
| ------------ | ------------------------ | ------------------------ |
| 游戏道具购买 | `item_id`, `item_count`  | 直接发放对应道具         |
| 关卡奖励翻倍 | `level_id`, `multiplier` | 按关卡和倍率计算奖励     |
| 订单验证     | `order_id`, `signature`  | 关联业务订单，防重复发放 |

#### 5.3.4 注意事项

1. **必须在 showAd 之前调用**：每次展示广告前设置，参数对当次展示生效
2. **键值都是 String 类型**：不支持嵌套或数组
3. **总量限制**：参数总体积不宜过大（推荐总体 < 500 字符），过长可能被截断
4. **不要放敏感信息**：参数会在 URL 中传输，不要直接放密码等敏感数据
5. **不要放签名相关字段**：签名验证使用的是标准字段（`timestamp`、`eventId`、`appUserId`、`rewards`），自定义参数不参与签名计算，只能做辅助关联，**不能用于安全验证**

#### 5.3.5 与 Dynamic UserID 的区别

|              | `setDynamicUserId`     | `setRewardedVideoServerParameters` |
| ------------ | ---------------------- | ---------------------------------- |
| 用途         | 标识用户身份           | 传递业务自定义数据                 |
| 值类型       | 单个字符串（1-64字符） | 键值对字典（String: String）       |
| 回调中字段名 | `dynamicUserId`        | `customParameters`（URL编码后）    |
| 参与签名     | ✅ 可配置参与签名      | ❌ 不参与签名                      |

---

## 6. 激励视频广告（Rewarded Video）

> SDK 版本要求：8.5.0+ | `getReward` API 需要 8.1.0+

### 6.1 创建广告对象

**必须**在收到初始化成功回调后执行。

`adUnitId` 在这里传入，不是在 `LevelPlay.initWith(...)` 初始化时传入。一个应用可以有多个广告单元，不同广告类型和不同业务场景可以创建不同的广告对象。

**Objective-C：**

```objc
self.rewardedAd = [[LPMRewardedAd alloc] initWithAdUnitId:@"你的AdUnitId"];
```

**Swift：**

```swift
self.rewardedAd = LPMRewardedAd(adUnitId: "你的AdUnitId")
```

> `AdUnitId` 在 LevelPlay 后台创建广告单元后获得。

常见参数边界：

| 参数 | 用途 | 设置时机 |
| ---- | ---- | -------- |
| `appKey` | 标识 LevelPlay 应用 | SDK 初始化时 |
| `adUnitId` | 标识具体广告单元 | 初始化成功后，创建广告对象时 |
| `placementName` | 标识展示场景/广告位 | 展示广告时 |
| `order_id` | 标识业务订单 | 展示广告前，通过 `customParameters` 设置 |

### 6.2 设置代理

**推荐在加载广告前**设置。每个广告对象应有自己的代理实例，所有回调都在**主线程**执行。

**Objective-C：**

```objc
self.rewardedAd = [[LPMRewardedAd alloc] initWithAdUnitId:@"adUnitId"];
self.rewardedAd.delegate = self;
```

**Swift：**

```swift
self.rewardedAd = LPMRewardedAd(adUnitId: "adUnitId")
self.rewardedAd.setDelegate(self)
```

### 6.3 代理回调方法（共 8 个）

**Objective-C：**

```objc
#pragma mark - LPMRewardedAdDelegate

// 广告加载成功
- (void)didLoadAdWithAdInfo:(LPMAdInfo *)adInfo {
    // 此时可以调用 showAd 展示广告
}

// 广告加载失败
- (void)didFailToLoadAdWithAdUnitId:(NSString *)adUnitId error:(NSError *)error {
    // error 中包含失败原因
}

// 广告信息更新（如 eCPM 变化）
- (void)didChangeAdInfo:(LPMAdInfo *)adInfo {}

// 广告开始展示
- (void)didDisplayAdWithAdInfo:(LPMAdInfo *)adInfo {}

// 广告展示失败
- (void)didFailToDisplayAdWithAdInfo:(LPMAdInfo *)adInfo error:(NSError *)error {}

// 用户点击了广告
- (void)didClickAdWithAdInfo:(LPMAdInfo *)adInfo {}

// 广告关闭
- (void)didCloseAdWithAdInfo:(LPMAdInfo *)adInfo {}

// ⭐ 用户完成观看，发放奖励
- (void)didRewardAdWithAdInfo:(LPMAdInfo *)adInfo reward:(LPMReward *)reward {
    NSLog(@"发放奖励: %@ x %ld", reward.name, (long)reward.amount);
}
```

**Swift：**

```swift
// MARK: - LPMRewardedAdDelegate

func didLoadAd(with adInfo: LPMAdInfo) {
    // 此时可以调用 showAd 展示广告
}

func didFailToLoadAd(withAdUnitId adUnitId: String, error: Error) {
    // error 中包含失败原因
}

func didChangeAdInfo(_ adInfo: LPMAdInfo) {}

func didDisplayAd(with adInfo: LPMAdInfo) {}

func didFailToDisplayAd(with adInfo: LPMAdInfo, error: Error) {}

func didClickAd(with adInfo: LPMAdInfo) {}

func didCloseAd(with adInfo: LPMAdInfo) {}

// ⭐ 用户完成观看，发放奖励
func didRewardAd(with adInfo: LPMAdInfo, reward: LPMReward) {
    print("发放奖励: \(reward.name) x \(reward.amount)")
}
```

### 6.4 加载广告

**Objective-C：**

```objc
[self.rewardedAd loadAd];
```

**Swift：**

```swift
self.rewardedAd.loadAd()
```

### 6.5 展示广告

**基本展示（无广告位）：**

**Objective-C：**

```objc
if ([self.rewardedAd isAdReady]) {
    [self.rewardedAd showAdWithViewController:self placementName:NULL];
}
```

**Swift：**

```swift
if self.rewardedAd.isAdReady() {
    self.rewardedAd.showAd(viewController: self, placementName: nil)
}
```

**带广告位的展示（推荐）：**

广告位用于后台控制展示频次和上限（pacing & capping）。

**Objective-C：**

```objc
NSString *placementName = @"main_menu_reward";

// 检查广告是否准备好 + 广告位是否未被上限限制
if ([self.rewardedAd isAdReady] && ![LPMRewardedAd isPlacementCapped:placementName]) {
    [self.rewardedAd showAdWithViewController:self placementName:placementName];
}
```

**Swift：**

```swift
let placementName = "main_menu_reward"

if self.rewardedAd.isAdReady(), !LPMRewardedAd.isPlacementCapped(placementName) {
    self.rewardedAd.showAd(viewController: self, placementName: placementName)
}
```

### 6.6 获取奖励信息（`getReward` API）

可在展示广告前获取后台配置的奖励内容（如 "金币 x 100"），用于更新 UI。

**Objective-C：**

```objc
LPMReward *reward = [self.rewardedAd getRewardWithPlacementName:@"main_menu"];
NSLog(@"观看广告可获得: %ld %@", (long)reward.amount, reward.name);
```

**Swift：**

```swift
let reward = self.rewardedAd.getReward(placementName: "main_menu")
print("观看广告可获得: \(reward.amount) \(reward.name)")
```

**奖励选择逻辑：**

1. 如果提供了有效的 placement 名称 → 返回该广告位配置的奖励
2. 如果 placement 为 `nil` 或未找到 → 返回广告单元的默认奖励
3. 如果在初始化完成前调用 → 返回空奖励（`name=""`, `amount=0`）

### 6.7 完整示例代码

**Swift 完整版：**

```swift
import UIKit
import IronSource

class RewardedAdViewController: UIViewController, LPMRewardedAdDelegate {

    var rewardedAd: LPMRewardedAd!

    // 1. 创建广告对象（在初始化成功后调用）
    func createRewardedAd() {
        self.rewardedAd = LPMRewardedAd(adUnitId: "你的AdUnitId")
        self.rewardedAd.setDelegate(self)
    }

    // 2. 加载广告
    func loadRewardedAd() {
        self.rewardedAd.loadAd()
    }

    // 3. 展示广告（无广告位）
    func showRewardedAd() {
        if self.rewardedAd.isAdReady() {
            self.rewardedAd.showAd(viewController: self, placementName: nil)
        }
    }

    // 4. 展示广告（带广告位）
    func showRewardedAd(withPlacementName placementName: String) {
        if self.rewardedAd.isAdReady(), !LPMRewardedAd.isPlacementCapped(placementName) {
            self.rewardedAd.showAd(viewController: self, placementName: placementName)
        }
    }

    // 5. 展示广告前动态设置后端订单号
    func showRewardedAd(orderId: String, placementName: String) {
        guard self.rewardedAd.isAdReady(),
              !LPMRewardedAd.isPlacementCapped(placementName) else {
            return
        }

        IronSource.clearRewardedVideoServerParameters()
        IronSource.setRewardedVideoServerParameters([
            "order_id": orderId
        ])

        self.rewardedAd.showAd(viewController: self, placementName: placementName)
    }

    // MARK: - LPMRewardedAdDelegate

    func didLoadAd(with adInfo: LPMAdInfo) {
        print("广告加载成功，可以展示")
    }

    func didFailToLoadAd(withAdUnitId adUnitId: String, error: Error) {
        print("广告加载失败: \(error.localizedDescription)")
    }

    func didChangeAdInfo(_ adInfo: LPMAdInfo) {}

    func didDisplayAd(with adInfo: LPMAdInfo) {
        print("广告开始展示")
    }

    func didFailToDisplayAd(with adInfo: LPMAdInfo, error: Error) {
        print("广告展示失败: \(error.localizedDescription)")
    }

    func didClickAd(with adInfo: LPMAdInfo) {
        print("用户点击了广告")
    }

    func didCloseAd(with adInfo: LPMAdInfo) {
        print("广告关闭")
    }

    func didRewardAd(with adInfo: LPMAdInfo, reward: LPMReward) {
        print("🎉 发放奖励: \(reward.name) x \(reward.amount)")
        // 这里通知后端发放奖励（推荐走 S2S 回调方式，见第 12 节）
    }
}
```

---

## 7. 插屏广告（Interstitial）

> SDK 版本要求：8.4.0+

插屏广告与激励视频的 API 几乎完全一致，区别是：

- 使用 `LPMInterstitialAd` 类
- 代理名称为 `LPMInterstitialAdDelegate`
- **没有** `didRewardAd` 回调

### 7.1 代理回调（共 7 个）

| 回调                                   | 说明         |
| -------------------------------------- | ------------ |
| `didLoadAd(with:)`                     | 广告加载成功 |
| `didFailToLoadAd(withAdUnitId:error:)` | 广告加载失败 |
| `didChangeAdInfo(_:)`                  | 广告信息变更 |
| `didDisplayAd(with:)`                  | 广告开始展示 |
| `didFailToDisplayAd(with:error:)`      | 广告展示失败 |
| `didClickAd(with:)`                    | 用户点击广告 |
| `didCloseAd(with:)`                    | 广告关闭     |

### 7.2 Swift 完整示例

```swift
import UIKit

class InterstitialAdViewController: UIViewController, LPMInterstitialAdDelegate {

    var interstitialAd: LPMInterstitialAd!

    func createInterstitialAd() {
        self.interstitialAd = LPMInterstitialAd(adUnitId: "你的AdUnitId")
        self.interstitialAd.setDelegate(self)
    }

    func loadInterstitialAd() {
        self.interstitialAd.loadAd()
    }

    func showInterstitialAd() {
        if self.interstitialAd.isAdReady() {
            self.interstitialAd.showAd(viewController: self, placementName: nil)
        }
    }

    func showInterstitialAd(withPlacementName placementName: String) {
        if self.interstitialAd.isAdReady(),
           !LPMInterstitialAd.isPlacementCapped(placementName) {
            self.interstitialAd.showAd(viewController: self, placementName: placementName)
        }
    }

    // MARK: - LPMInterstitialAdDelegate

    func didLoadAd(with adInfo: LPMAdInfo) {}
    func didFailToLoadAd(withAdUnitId adUnitId: String, error: Error) {}
    func didChangeAdInfo(_ adInfo: LPMAdInfo) {}
    func didDisplayAd(with adInfo: LPMAdInfo) {}
    func didFailToDisplayAd(with adInfo: LPMAdInfo, error: Error) {}
    func didClickAd(with adInfo: LPMAdInfo) {}
    func didCloseAd(with adInfo: LPMAdInfo) {}
}
```

---

## 8. 横幅广告（Banner / MREC）

> SDK 版本要求：8.4.0+

横幅广告使用 `LPMBannerAdView`（是一个 UIView 子类），需要添加到视图层级中。

### 8.1 广告尺寸

| 尺寸常量                          | 宽 × 高 (dp) | 说明                           |
| --------------------------------- | ------------ | ------------------------------ |
| `LPMAdSize.bannerSize()`          | 320 × 50     | 标准横幅                       |
| `LPMAdSize.largeSize()`           | 320 × 90     | 大型横幅                       |
| `LPMAdSize.mediumRectangleSize()` | 300 × 250    | MREC 矩形                      |
| `LPMAdSize.createAdaptive()`      | 自适应       | **推荐**，根据屏幕宽度自动适配 |

### 8.2 创建横幅广告

**Swift：**

```swift
// 创建广告配置（可选）
let adConfig = LPMBannerAdViewConfigBuilder()
    .set(adSize: LPMAdSize.createAdaptive())        // 自适应尺寸
    .set(placementName: "home_banner")              // 广告位名称（仅用于上报）
    .build()

// 创建横幅广告视图
self.bannerAd = LPMBannerAdView(adUnitId: "你的AdUnitId", config: adConfig)
self.bannerAd.setDelegate(self)
```

### 8.3 添加到视图层级

**Swift：**

```swift
func addBannerToView() {
    self.bannerAd.translatesAutoresizingMaskIntoConstraints = false
    self.view.addSubview(self.bannerAd)

    NSLayoutConstraint.activate([
        self.bannerAd.bottomAnchor.constraint(equalTo: self.view.safeAreaLayoutGuide.bottomAnchor),
        self.bannerAd.centerXAnchor.constraint(equalTo: self.view.centerXAnchor),
        self.bannerAd.widthAnchor.constraint(equalToConstant: 320),
        self.bannerAd.heightAnchor.constraint(equalToConstant: 50)
    ])
}
```

### 8.4 加载广告

**Swift：**

```swift
self.bannerAd.loadAd(with: self)
```

### 8.5 代理回调（共 8 个）

| 回调                                   | 必选/可选 | 说明               |
| -------------------------------------- | --------- | ------------------ |
| `didLoadAd(with:)`                     | 必选      | 广告加载成功       |
| `didFailToLoadAd(withAdUnitId:error:)` | 必选      | 加载失败           |
| `didClickAd(with:)`                    | 必选      | 广告被点击         |
| `didDisplayAd(with:)`                  | 必选      | 广告展示在屏幕上   |
| `didFailToDisplayAd(with:error:)`      | 可选      | 展示失败           |
| `didLeaveApp(with:)`                   | 可选      | 用户点击后离开应用 |
| `didExpandAd(with:)`                   | 可选      | 广告全屏展开       |
| `didCollapseAd(with:)`                 | 可选      | 广告恢复原始大小   |

### 8.6 自动刷新控制

如果后台配置了横幅自动刷新：

**Swift：**

```swift
self.bannerAd.pauseAutoRefresh()   // 暂停刷新
self.bannerAd.resumeAutoRefresh()  // 恢复刷新
```

### 8.7 销毁横幅

销毁后**无法再展示**，需要展示新广告时重新创建。

**Swift：**

```swift
self.bannerAd.destroy()
```

推荐在 `deinit` 中调用：

```swift
deinit {
    self.bannerAd.destroy()
}
```

### 8.8 Swift 完整示例

```swift
import UIKit

class BannerAdViewController: UIViewController, LPMBannerAdViewDelegate {

    var bannerAd: LPMBannerAdView!

    deinit {
        self.bannerAd.destroy()
    }

    func createBannerAd() {
        let bannerSize = LPMAdSize.createAdaptive()

        let adConfig = LPMBannerAdViewConfigBuilder()
            .set(adSize: bannerSize)
            .set(placementName: "home_banner")
            .build()

        self.bannerAd = LPMBannerAdView(adUnitId: "你的AdUnitId", config: adConfig)
        self.bannerAd.setDelegate(self)

        addBannerView(withSize: bannerSize)
    }

    func loadBannerAd() {
        self.bannerAd.loadAd(with: self)
    }

    func addBannerView(withSize bannerSize: LPMAdSize) {
        DispatchQueue.main.async {
            self.bannerAd.translatesAutoresizingMaskIntoConstraints = false
            self.view.addSubview(self.bannerAd)

            NSLayoutConstraint.activate([
                self.bannerAd.bottomAnchor.constraint(equalTo: self.view.safeAreaLayoutGuide.bottomAnchor),
                self.bannerAd.centerXAnchor.constraint(equalTo: self.view.centerXAnchor),
                self.bannerAd.widthAnchor.constraint(equalToConstant: CGFloat(bannerSize.width)),
                self.bannerAd.heightAnchor.constraint(equalToConstant: CGFloat(bannerSize.height))
            ])
        }
    }

    // MARK: - LPMBannerAdViewDelegate

    func didLoadAd(with adInfo: LPMAdInfo) {}
    func didFailToLoadAd(withAdUnitId adUnitId: String, error: Error) {}
    func didClickAd(with adInfo: LPMAdInfo) {}
    func didDisplayAd(with adInfo: LPMAdInfo) {}
    func didFailToDisplayAd(with adInfo: LPMAdInfo, error: Error) {}
    func didLeaveApp(with adInfo: LPMAdInfo) {}
    func didExpandAd(with adInfo: LPMAdInfo) {}
    func didCollapseAd(with adInfo: LPMAdInfo) {}
}
```

---

## 9. 隐私法规合规（GDPR / CCPA / COPPA）

> SDK 9.4.0+ 使用 `LPMPrivacySettings` 类。旧版 API（`setConsent`、`setMetaDataWithKey`）已废弃。

### 9.1 GDPR（欧盟通用数据保护条例）

**为每个广告网络单独设置授权：**

```swift
let consents: [String: NSNumber] = [
    "UnityAds": true,
    "AdMob": false,
    "AppLovin": true
]
LPMPrivacySettings.setGDPRConsents(consents)
```

- `true` = 用户已同意数据处理
- `false` = 用户不同意
- **每次调用会完全覆盖**之前设置的所有 GDPR 同意值
- 仅包含在字典中的网络会拥有同意记录

**支持的广告网络 Key（26 个）：**

| 网络 Key   | 网络 Key   | 网络 Key     |
| ---------- | ---------- | ------------ |
| UnityAds   | AdMob      | AppLovin     |
| APS        | BidMachine | Bigo         |
| Chartboost | Facebook   | Fyber        |
| HyprMx     | InMobi     | Line         |
| Mintegral  | MobileFuse | Moloco       |
| MyTarget   | Ogury      | Pangle       |
| PubMatic   | Smaato     | SuperAwesome |
| Verve      | Voodoo     | Vungle       |
| Yandex     | YSO        |              |

> SDK 7.7.0+ 支持从 Google UMP 等 CMP 自动共享同意状态。

### 9.2 CCPA / 美国隐私法（"请勿出售我的个人信息"）

涵盖的法律包括 CCPA（加州）、LGPD（巴西）、VCDPA（弗吉尼亚）等 22 部美国各州隐私法。

```swift
// 用户选择退出数据销售/共享
LPMPrivacySettings.setCCPA(true)

// 用户同意数据销售/共享
LPMPrivacySettings.setCCPA(false)
```

> 最佳实践：在**初始化 SDK 之前**调用此 API。

### 9.3 COPPA（儿童在线隐私保护法）

面向儿童的应用设置：

```swift
// 用户是儿童
LPMPrivacySettings.setCOPPA(true)

// 用户不是儿童
LPMPrivacySettings.setCOPPA(false)
```

> 最佳实践：在**初始化 SDK 之前**调用此 API。
>
> 注意：开发者有责任自行判断法律义务。

### 9.4 隐私设置调用顺序（参考）

```swift
// 1. 在 SDK 初始化之前设置隐私选项
LPMPrivacySettings.setGDPRConsents(["UnityAds": true, "AdMob": true])
LPMPrivacySettings.setCCPA(false)
LPMPrivacySettings.setCOPPA(false)

// 2. 然后初始化 SDK
LevelPlay.initWith(initRequest) { config, error in
    // ...
}
```

---

## 10. 聚合广告网络（Mediation Networks）

### 10.1 概述

LevelPlay 支持接入 25+ 广告网络。聚合适配器（Adapter）**已包含**对应网络的 SDK，**无需单独集成**。

### 10.2 接入方式

**通过 CocoaPods：** 在 LevelPlay 后台选择广告网络后，复制自动生成的 Podfile 脚本即可。

**手动下载：** 从 [GitHub 仓库](https://github.com/ironsource-mobile/levelplay-ios-adapters) 下载各网络的适配器和 SDK。

### 10.3 支持的主要广告网络

| 网络                 | 支持格式                               | 网络                | 支持格式                               |
| -------------------- | -------------------------------------- | ------------------- | -------------------------------------- |
| **Google (AdMob)**   | Banner, Interstitial, Rewarded, Native | **Meta (Facebook)** | Banner, Interstitial, Rewarded, Native |
| **Unity Ads**        | Banner, Interstitial, Rewarded         | **AppLovin**        | Banner, Interstitial, Rewarded         |
| **Vungle (Liftoff)** | Banner, Interstitial, Rewarded         | **Chartboost**      | Banner, Interstitial, Rewarded         |
| **Pangle**           | Banner, Interstitial, Rewarded         | **InMobi**          | Banner, Interstitial, Rewarded         |
| **Mintegral**        | Banner, Interstitial, Rewarded         | **APS (Amazon)**    | Banner, Interstitial, Rewarded         |
| **Bigo**             | Banner, Interstitial, Rewarded         | **Fyber**           | Banner, Interstitial, Rewarded         |
| **Ogury**            | Banner, Interstitial, Rewarded         | **Verve**           | Banner, Interstitial, Rewarded         |
| **Yandex**           | Banner, Interstitial, Rewarded         | **Smaato**          | Banner, Interstitial, Rewarded         |
| **Tencent**          | Banner, Interstitial, Rewarded         | **PubMatic**        | Banner, Interstitial, Rewarded         |
| **SuperAwesome**     | Interstitial, Rewarded（无 Banner）    | **Moloco**          | Banner, Interstitial, Rewarded         |
| **MobileFuse**       | Banner, Interstitial, Rewarded         | **HyprMX**          | Banner, Interstitial, Rewarded         |

> 完整列表和版本号请查阅 Unity 官方文档。

---

## 11. 集成测试（Test Suite）

> SDK 7.3.0+ | 仅支持竖屏

### 11.1 启用测试套件

**在初始化之前**调用：

**Objective-C：**

```objc
[LevelPlay setMetaDataWithKey:@"is_test_suite" value:@"enable"];
```

**Swift：**

```swift
LevelPlay.setMetaDataWithKey("is_test_suite", value: "enable")
```

### 11.2 启动测试套件

**在初始化成功后**调用：

**Objective-C：**

```objc
[LevelPlay setMetaDataWithKey:@"is_test_suite" value:@"enable"];

LPMInitRequestBuilder *requestBuilder = [[LPMInitRequestBuilder alloc] initWithAppKey:@"appKey"];
[requestBuilder withUserId:@"UserId"];
LPMInitRequest *initRequest = [requestBuilder build];

[LevelPlay initWithRequest:initRequest completion:^(LPMConfiguration * _Nullable config, NSError * _Nullable error) {
    if (error) {
        // 初始化失败
    } else {
        // 初始化成功，启动测试套件
        [LevelPlay launchTestSuite:self];
    }
}];
```

**Swift：**

```swift
LevelPlay.setMetaDataWithKey("is_test_suite", value: "enable")

let requestBuilder = LPMInitRequestBuilder(appKey: "appKey")
    .withUserId("UserId")
let initRequest = requestBuilder.build()

LevelPlay.initWith(initRequest) { [weak self] config, error in
    guard let self = self else { return }
    if let error = error {
        // 初始化失败
    } else {
        // 初始化成功，启动测试套件
        LevelPlay.launchTestSuite(self)
    }
}
```

### 11.3 测试套件功能

| 标签页             | 功能                                                 |
| ------------------ | ---------------------------------------------------- |
| **App Info**       | 查看应用信息、SDK 版本、隐私配置                     |
| **Ad Networks**    | 查看所有聚合网络及其集成状态（绿色=成功，红色=失败） |
| **Ad Units**       | 查看所有广告单元列表                                 |
| **单广告单元测试** | 选中广告单元 → 查看竞价瀑布流 → 加载广告 → 展示广告  |
| **单网络测试**     | 选中网络 → 选择广告格式 → 加载测试广告               |

**状态指示灯：**

- 🟠 **橙色**：尚未测试
- 🟢 **绿色**：加载/展示成功
- 🔴 **红色**：加载/展示失败（显示失败原因）

> 注意：启动测试套件前不要调用其他 LevelPlay API，避免与生产模式冲突。

---

## 12. 服务端回调验证（S2S）

### 12.1 配置位置

在 IronSource LevelPlay 后台 → 对应应用 → **Server-to-Server Callbacks** 中配置回调 URL 和签名密钥。

### 12.2 回调参数

IronSource 会向你配置的 URL 发送回调请求。当前项目按下面这组参数验签和解析：

```text
appUserId=908871522
country=SG
dynamicUserId=890366592747472530
eventId=26abYb3da107f192380eY0
publisherSubId=0
rewards=10
signature=f41ecbf2646bae2ac2cf6c57437ba5d8
timestamp=202604300647
```

#### 核心参数（参与签名）

| 参数        | 类型   | 来源              | 说明                           |
| ----------- | ------ | ----------------- | ------------------------------ |
| `timestamp` | String | IronSource 生成   | 回调时间戳                     |
| `eventId`   | String | IronSource 生成   | 唯一事件 ID，用于幂等          |
| `appUserId` | String | 初始化/后台占位符 | 用户 ID，参与签名              |
| `rewards`   | String | 后台配置          | 奖励数量，参与签名             |

#### 签名参数

| 参数        | 类型   | 说明                                     |
| ----------- | ------ | ---------------------------------------- |
| `signature` | String | MD5 签名（32位 hex），**不参与签名计算** |

#### 辅助参数

| 参数               | 类型   | 来源                                   | 说明                                                     |
| ------------------ | ------ | -------------------------------------- | -------------------------------------------------------- |
| `country`          | String | IronSource                             | 国家/地区代码                                            |
| `dynamicUserId`    | String | SDK `setDynamicUserId`                 | 动态用户 ID，可用于业务用户映射                          |
| `publisherSubId`   | String | 回调 URL 占位符                        | 发布方自定义子 ID                                        |
| `customParameters` | String | SDK `setRewardedVideoServerParameters` | URL编码的自定义参数字符串，**不参与签名**，详见第 5.3 节 |
| `adUnit`           | String | 广告单元                               | 广告单元 ID                                              |
| `placement`        | String | 广告位                                 | 广告位名称                                               |
| `network`          | String | IronSource                             | 广告网络名称                                             |

> **重要：`customParameters` 不参与签名计算**，因此不能用于安全验证（如防伪造）。只能用于业务关联。如果需要将自定义数据纳入签名保护，应使用 `dynamicUserId`（可在后台配置为签名字段）。

### 12.3 签名验证（PHP 示例）

```php
// 1. 按 LevelPlay S2S 规则拼接
$payload = $params['timestamp']
    . $params['eventId']
    . $params['appUserId']
    . $params['rewards']
    . $secretKey;

// 2. 计算 MD5
$expected = md5($payload);

// 3. 使用 hash_equals 防时序攻击
if (hash_equals($expected, $params['signature'])) {
    // 签名有效，发放奖励
}

// 4. 解析自定义参数（仅用于业务关联，不用于安全判断）
$custom = [];
if (!empty($params['customParameters'])) {
    parse_str(rawurldecode($params['customParameters']), $custom);
    // $custom = ['item_id' => 'sword_001', 'level' => '5']
}
```

### 12.4 安全注意事项

1. **始终验证签名**：防止客户端伪造回调
2. **检查 eventId 唯一性**：防止重放攻击（同一 eventId 不能重复发放奖励）
3. **验证时间戳**：拒绝与服务器时间差距超过 5 分钟的回调
4. **返回 200**：IronSource 对非 2xx 响应会重试最多 10 次，即使是重复交易也应返回 200
5. **`customParameters` 不参与签名**：自定义参数只用于业务关联，切勿用于安全判断（如"验证 item_id 是否合法发放"）。需要安全验证的自定义数据应使用 `dynamicUserId`（配置为签名字段）

---

## 13. 常见集成顺序问题

### 13.1 初始化是否必须等待后端订单号？

不需要。LevelPlay 初始化只需要 SDK 启动所需的稳定参数，核心是 `appKey`。后端订单号属于每次激励广告展示前的业务参数，应在用户准备看广告时动态获取，再通过 `setRewardedVideoServerParameters` 设置。

错误做法：

```swift
// 不推荐：为了等 order_id 延迟 SDK 初始化
let orderId = requestOrderFromServer()
let initRequest = LPMInitRequestBuilder(appKey: "appKey").build()
LevelPlay.initWith(initRequest) { _, _ in }
```

推荐做法：

```swift
// App 启动或广告模块启动时初始化
let initRequest = LPMInitRequestBuilder(appKey: "appKey").build()
LevelPlay.initWith(initRequest) { config, error in
    guard error == nil else {
        return
    }

    self.rewardedAd = LPMRewardedAd(adUnitId: "rewarded_ad_unit")
    self.rewardedAd.setDelegate(self)
    self.rewardedAd.loadAd()
}

// 用户点击领取奖励时，再请求后端订单号
func onRewardButtonTapped() {
    let orderId = requestOrderFromServer()

    IronSource.clearRewardedVideoServerParameters()
    IronSource.setRewardedVideoServerParameters([
        "order_id": orderId
    ])

    rewardedAd.showAd(viewController: self, placementName: "daily_bonus")
}
```

### 13.2 `adUnitId` 在哪里初始化？

`adUnitId` 不在 SDK 初始化里传。它在初始化成功后，创建具体广告对象时传入：

| 广告类型 | 创建方式 |
| -------- | -------- |
| 激励广告 | `LPMRewardedAd(adUnitId: "rewarded_ad_unit")` |
| 插屏广告 | `LPMInterstitialAd(adUnitId: "interstitial_ad_unit")` |
| 横幅广告 | `LPMBannerAdView(adUnitId: "banner_ad_unit", config: adConfig)` |

`adUnitId` 通常来自 LevelPlay 后台配置，可以写在客户端配置里。如果希望由后端下发，也应在 SDK 初始化成功后、创建广告对象之前获取。

### 13.3 推荐的完整时序

```text
1. App 启动
2. LevelPlay.initWith(appKey)
3. 初始化成功
4. 创建广告对象：LPMRewardedAd(adUnitId)
5. 设置 delegate
6. loadAd()
7. 用户点击领取奖励
8. 请求后端创建订单，拿到 order_id
9. clearRewardedVideoServerParameters()
10. setRewardedVideoServerParameters(["order_id": order_id])
11. showAd(viewController:placementName:)
12. 用户完成观看
13. LevelPlay S2S 回调后端
14. 后端验签并解析 customParameters.order_id
15. 后端按 eventId/order_id 做幂等发奖
```

---

## 14. Demo 应用

官方提供了完整的 Demo 应用供参考：

🔗 [https://github.com/ironsource-mobile/Mediation-Demo-Apps](https://github.com/ironsource-mobile/Mediation-Demo-Apps)

---

## 附录：API 速查表

### 初始化

| 操作     | Swift                                                   |
| -------- | ------------------------------------------------------- |
| 构建请求 | `LPMInitRequestBuilder(appKey:).withUserId(_:).build()` |
| 初始化   | `LevelPlay.initWith(_:completion:)`                     |

### 激励视频

| 操作                 | Swift                                    |
| -------------------- | ---------------------------------------- |
| 创建对象             | `LPMRewardedAd(adUnitId:)`               |
| 设置代理             | `.setDelegate(self)`                     |
| 加载                 | `.loadAd()`                              |
| 检查是否就绪         | `.isAdReady()`                           |
| 检查广告位是否达上限 | `LPMRewardedAd.isPlacementCapped(_:)`    |
| 展示                 | `.showAd(viewController:placementName:)` |
| 获取奖励信息         | `.getReward(placementName:)`             |

### 插屏广告

| 操作     | Swift                                |
| -------- | ------------------------------------ |
| 创建对象 | `LPMInterstitialAd(adUnitId:)`       |
| 其他同上 | 与激励视频一致（无 reward 相关 API） |

### 横幅广告

| 操作     | Swift                               |
| -------- | ----------------------------------- |
| 创建对象 | `LPMBannerAdView(adUnitId:config:)` |
| 设置代理 | `.setDelegate(self)`                |
| 加载     | `.loadAd(with:)`                    |
| 暂停刷新 | `.pauseAutoRefresh()`               |
| 恢复刷新 | `.resumeAutoRefresh()`              |
| 销毁     | `.destroy()`                        |

### 隐私

| 操作  | Swift                                    |
| ----- | ---------------------------------------- |
| GDPR  | `LPMPrivacySettings.setGDPRConsents(_:)` |
| CCPA  | `LPMPrivacySettings.setCCPA(_:)`         |
| COPPA | `LPMPrivacySettings.setCOPPA(_:)`        |

---

> 文档版本：基于 Unity LevelPlay iOS SDK 9.4.0 官方文档整理 | 更新日期：2026-04-29
