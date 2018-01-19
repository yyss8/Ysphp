# Ysphp
    一个没有文档的自用框架, 不定期更新, 公司有啥需求做什么
    
    Not sure what is this for

### 包含模块
##### Namespace\module name

1. Ysphp\Ys_Global.php (一边做一边补充)

        包含了一些常用功能, 以及简单封装过的php curl get, post, put, delete方式, 可通过继承使用到所有自定义对象

2. Ysphp\Ajax.php (部分完成, 勉勉强强能用)

        一个用于处理Restful Ajax请求的简易模块, 通过参数action来查找内存中的函数名称来选择ajax请求的处理函数
        
        该模块通过请求类型的prefix进行查找处理函数
        
        例如一个包含action参数为update_users的post请求, 模块就会自动去寻找名称为post_update_users的函数, 如果找不到就返回错误
  
        使用json()方法默认输出json格式的信息, 如果请求参数中包含output参数并且为true则输出raw html
  
3. Ysphp\Builder (未完成, 巨坑)

        包含一些常用HTML输出方法
  
4. Ysphp\Qiniu (完成图片上传功能) 

        自用七牛上传工具, 暂时只完成图片base64图片链接上传
  
5. Ysphp\Database\Mysql (做这个是因为记不住php自带的mysqli命令)

        一个简单封装过的Mysqli库
        
        基本完成了select (多行/单行), insert, update等功能
