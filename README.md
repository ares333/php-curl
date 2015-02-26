About
-----

This is undoubtedly the best php curl library in the world.It is widely used by many developers.The library is a wrapper of curl_multi_* functions with best performance,maximum flexibility,maximum ease of use and negligible performance consumption.All in all it's a very very powerful library.

PHP Version
-----------
PHP 5.1.0 +

Contact Us
----------
Email: admin@curlmulti.com<br>
QQ Group:215348766

Feature
-------
1. Extremely low cpu and memory usage.
1. Best program performance(tested spider 2000+ html pages per second and 1000MBit/s pic download speed).
1. Internal download support(use curl download callback,best performance).
1. Support global parallel and seperate parallel for defferent task type.
1. Support running info callback.All info you need is returned, include overall and every task infomation.
1. Support add task in task callback.
1. Support user callback can be specified.You can do anything in that.
1. Support process callback backoff.Used to satisfy prerequists.
1. Support error callback.All error info is returned.
1. Support max try for tasks.
1. Support user variable flow arbitrarily.
1. Support global CURLOPT_* and task CURLOPT_*.
1. Powerfull cache.Global and task cache config supported.
1. All public property config can be changed on the fly!
1. You can develop amazing curl application based on the library.

Mechanism
---------

Without pthreads php is single-threaded language,so the library widely use callbacks.There are only two common functions CurlMulti_Core::add() and CurlMulti_Core::start().add() just add a task to internal taskpool.start() starts callback cycle with the concurrent number of CurlMulti_Core::$maxThread and is blocked until all added tasks(a typical task is a url) are finished.If you have huge number of tasks you will use CurlMulti_Core::$cbTask to specify a callback function to add() urls,this callback is called when the number of running concurrent is less than CurlMulti_Core::$maxThread.When a task finished the 'process callback' specified in add() is immediately called,and then fetch a task from internal taskpool,and then add the task to the running concurrent.When all added tasks finished the start() finished.

Files
-----

API
---

Demo
----
