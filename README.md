# Ysphp

    一个没有文档的自用框架, 不定期更新, 公司有啥需求做什么
    
    Not sure what is this for

### 使用方法

        加载Ysphp目录下的load.php

### 包含模块
##### Namespace\module name

1. Ysphp\Utilities.php (添加了多线程方法multiCurl, 主要用于七牛的图片抓取以及上传)

        包含了一些常用功能, 以及简单封装过的php curl get, post, put, delete方式, 可通过继承使用到所有自定义对象

2. Ysphp\Ajax.php (部分完成, 勉勉强强能用)

        一个用于ajax请求的简易模块,主要参考wordpress的ajax匹配方式 ,通过参数action来查找内存中的函数名称来选择ajax请求的处理函数
        
        该模块通过请求类型的prefix进行查找处理函数
        
        例如一个包含action参数为update_users的post请求, 模块就会自动去寻找名称为post_update_users的函数, 如果找不到就返回错误
  
        使用json()方法默认输出json格式的信息, 如果请求参数中包含output参数并且为true则输出raw html
  
3. Ysphp\Builder (未完成, 巨坑)

        包含一些常用HTML输出方法
  
4. Ysphp\Qiniu (主要用于图片上传, 新增了多线程图片抓取功能, 建议拆分请求成多个chunk免得被封IP) 

        自用七牛上传工具, 使Utilities中封装的curl方法处理七牛请求
        
        1. uploadByUrl(bucket名, url路径, params参数): 路径上传
        
            通过文件地址（网址或文件路径）上传至七牛云, 如果不添加insertOnly参数则遇到bucket下同名称资源则覆盖原资源
                     
            返回Object形式  成功返回 hash码以及key  失败返回{ error: 失败信息 }
                    
        2. uploadByFile(bucket名, fileName文件名, params参数):文件上传
        
            类似uploadByUrl, 将$_FILES中文件的路径传至uploadByUrl
        
        3. delete (bucket名, key文件名)
        
            通过bucket和key的组合删除七牛云指定资源

5. Ysphp\Database\Mysql (做这个是因为记不住php自带的mysqli命令)

        一个简单封装过的Mysqli库
        基本完成了select (多行/单行), insert, update等功能
        
6. Ysphp\Validation (后端表单验证)
    
    支持二代身份证、中国手机以及查用表单类型的验证功能
    
