<?php
// 安全防护 - 禁止直接访问数据目录
header('HTTP/1.0 403 Forbidden');
exit('Forbidden');
