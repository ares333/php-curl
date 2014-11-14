CurlMulti
=========

This is undoubtedly the best php curl library in the world.It is widely used by many developers.The library is a wrapper of curl_multi_* functions with best performance,maximum flexibility,maximum ease of use and negligible performance consumption.

Mechanism
---------

Without pthreads php is single-threaded language,so the library widely use callback.There are only two common functions CurlMulti::add() and CurlMulti::start().add() just add a task to internal taskpool.start() starts callback cycle with CurlMulti::$maxThread concurrent and is blocked until all added tasks(a typical task is a url) are finished.If you have huge number of tasks you will use CurlMulti::$cbTask to specify a callback function to add() urls,this callback is called when the running concurrent num is less than CurlMulti::$maxThread.When a task finished the process callback specified in add() is immediately called,and then fetch a task from internal taskpool,and then add the task to the running concurrent.When all added tasks finished the start() finished.

Usage
-----


