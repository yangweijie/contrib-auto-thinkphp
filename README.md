# contrib-auto-thinkphp
OpenTelemetry auto-instrumentation for ThinkPHP

# OpenTelemetry ThinkPHP auto-instrumentation

请阅读 https://opentelemetry.io/docs/instrumentation/php/automatic/ 来知晓如何安装扩展和配置 SDK。

## 概览

Auto-instrumentation hooks 通过 composer 注册，并且 spans 会自动被创建。
当前版本不支持指标的自动上报。

## 要求

- [opentelemetry extension](https://pecl.php.net/package/opentelemetry)

## 配置

该扩展可以被禁用 [runtime configuration](https://opentelemetry.io/docs/instrumentation/php/sdk/#configuration):

```shell
OTEL_PHP_DISABLED_INSTRUMENTATIONS=thinkphp
```

### 环境变量

[环境变量](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/)

### 如何启用 

首先，参考 [openobserver 快速开始](https://openobserve.ai/docs/quickstart/#__tabbed_1_1)
在本地或者服务器上部署 一套openobserver 程序。

然后登录后台，在“采集”-> “日志”-> “自定义”-> “OTLP Collector” 中复制 出 endpoint 和  Authorization 

![配置](images/config.png)

在`composer.json`中配置 extra log 的driver 文件 用于临时修复TP var_export 获得引用对象变量报错的bug。等官方修复了我再把这段检测去掉。

~~~
    "extra": {
        "include_files": [
            "vendor/yangweijie/opentelemetry-auto-thinkphp/src/polyfill/log/driver/File.php"
        ]
    },
~~~

#### 命令行

##### 官方的方式 执行命令前带上环境变量配置
参考 包根目录下的 `otlp.bat`（win）和 `otlp.sh` ，可以将其拷贝至项目根目录, 然后将配置里的endpoint 替换为复制出来的。

##### ThinkPHP的方式
当然是在.env 里配置了。


~~~
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=default

OTEL_TRACES_EXPORTER=otlp
OTEL_LOGS_EXPORTER=otlp
OTEL_METRICS_EXPORTER=none

OTEL_PHP_DETECTORS=env,os,process,host
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:5080/api/default
OTEL_PROPAGATORS=baggage,tracecontext
OTEL_EXPORTER_OTLP_HEADERS="Authorization=Basic OTE3NjQ3Mjg4QHFxLmNvbTpPa3BYMlp1MVIzTVRnUFhO,stream-name=default"
~~~
记得和APP_DEBUG 同级 不要放在 [XXX] 后面

然后正常 `php think help` 和 `php think run` 都行

#### web服务

##### think-server

跟上面说的一样:

- 在根目录下 `otlp.bat run` 或 `otlp.sh run`
- 或者 配了.env  `php think run` 后浏览器直接 ip+端口或 nginx 配反向代理访问

##### nginx

官方的做法是在 修改php.ini，这样我觉的不妥，全部的应用都开启了（当然可以开启后，在单个项目的nginx config里配置 SERVER变量来关闭）。

我实践出来的最佳方法（nginx）：

~~~ nginx #otlp.conf 内容
fastcgi_param OTEL_PHP_DETECTORS env,os,process,host;
fastcgi_param OTEL_PHP_LOG_DESTINATION psr3;
# fastcgi_param OTEL_PHP_LOG_DESTINATION error_log;
fastcgi_param OTEL_PHP_AUTOLOAD_ENABLED true;
fastcgi_param OTEL_SERVICE_NAME default;
fastcgi_param OTEL_TRACES_EXPORTER otlp;
fastcgi_param OTEL_METRICS_EXPORTER none;
fastcgi_param OTEL_LOGS_EXPORTER otlp;
fastcgi_param OTEL_EXPORTER_OTLP_ENDPOINT http://localhost:5080/api/default;
fastcgi_param OTEL_EXPORTER_OTLP_HEADERS 'Authorization=Basic OTE3NjQ3Mjg4QHFxLmNvbTpPa3BYMlp1MVIzTVRnUFhO,stream-name=default';
~~~

将这段内容替换调ENDPOINT 和 HEADERS 后，放置在 nginx 的 `config/php` 目录下 然后，在 自己域名配置的php部分包含这个文件

~~~
location ~ [^/]\.php(/|$)
{
	fastcgi_pass 127.0.0.1:9013;
	fastcgi_index  index.php;
	include pathinfo.conf;
	include fastcgi.conf;
	include php/open_observe.conf;
}
~~~

试过直接放在server段 无效的。

所以如果结合php.ini 的修改，就是将通用的放在ini里。

在nginx配置里配一下  `fastcgi_param OTEL_PHP_DISABLED_INSTRUMENTATIONS thinkphp`禁用 和 header 来区分 上报的项目和用户

## 被扩展的上下文

`deployment.environment.name` 开发环境

通过 APP_DEBUG 和 ENV_NAME 非空(就是在入口.env.xxx 文件来作为环境变量文件) 来 判断 满足一个就是test 不满足 就是prod


## Log

### 搜索

[参考1](https://openobserve.ai/docs/example-queries/) 、[参考2](https://geekdaxue.co/read/OpenObserve-doc/log-search)

#### 全列搜索 match_all 大小不敏感

~~~
match_all('error')
~~~

#### 全文索引搜索 大小写敏感
~~~
match_all_raw('error')
~~~

#### 全文索引搜索 忽略大小写敏感
~~~
match_all_raw_ignore_case('error')
~~~

#### 指定列搜索 区分大小写， 如果是日志内容应该搜 body

~~~ 
str_match(fieldname, 'error')
~~~

#### 指定列搜索 不区分大小写

~~~
str_match_ignore_case(fieldname, 'error')
~~~

#### 精确搜索某列值

~~~ 数值
code=200
~~~

~~~ 字符串
code='200'
~~~

#### 指定流 来源
~~~
stream='stderr'
~~~

#### 使用查询函数（lua扩展的）

~~~
extract_ip(log) | code=200
~~~

#### SQL 模式

~~~
SELECT * FROM "default" WHERE trace_id='7613688775511defb36d27f7869e612d'
~~~

![效果图](images/log1.png)
![查询](images/log2.png)
![详情](images/log_detail.png)
![table详情](images/log_detail_table.png)
## trace

> trace 主要是同一个请求内 不同span 的组合 同属于一个trace_id。

> 每个span 会 记录耗时。

### span 主要构成
#### Name
#### Parent span ID (empty for root spans)
#### Start and End Timestamps
#### Span Context 上下文
#### Attributes 属性
#### Span Events 事件
可以认为是 Span 的结构化日志
何时使用span event ， 当一个事件发生 无需记录开始和结束，反正用 Span。
#### Span Links
#### Span Status
#### SpanKind

| `SpanKind` | 调用方向 | 通信风格 |
| --- |---| --- |
| `CLIENT` | 发起 | request/response |
| `SERVER` | 进入 | request/response |
| `PRODUCER` | 发起 | deferred execution |
| `CONSUMER` | 进入 | deferred execution |
| `INTERNAL` |  |  |

~~~ example
{
  "name": "/v1/sys/health",
  "context": {
    "trace_id": "7bba9f33312b3dbb8b2c2c62bb7abe2d",
    "span_id": "086e83747d0e381e"
  },
  "parent_id": "",
  "start_time": "2021-10-22 16:04:01.209458162 +0000 UTC",
  "end_time": "2021-10-22 16:04:01.209514132 +0000 UTC",
  "status_code": "STATUS_CODE_OK",
  "status_message": "",
  "attributes": {
    "net.transport": "IP.TCP",
    "net.peer.ip": "172.17.0.1",
    "net.peer.port": "51820",
    "net.host.ip": "10.177.2.152",
    "net.host.port": "26040",
    "http.method": "GET",
    "http.target": "/v1/sys/health",
    "http.server_name": "mortar-gateway",
    "http.route": "/v1/sys/health",
    "http.user_agent": "Consul Health Check",
    "http.scheme": "http",
    "http.host": "10.177.2.152:26040",
    "http.flavor": "1.1"
  },
  "events": [
    {
      "name": "",
      "message": "OK",
      "timestamp": "2021-10-22 16:04:01.209512872 +0000 UTC"
    }
  ]
}

~~~

### 效果图
![trace](images/trace.png)
![trace 详情1](images/trace_detail.png)
![trace 详情2](images/trace_detail2.png)


