<?php
/**
 * 模块：模块管理 (modules)
 * 功能：save_modules
 * 双模式文件：handle=POST 处理，render=页面渲染
 * 说明：可视化启用/停用后台模块、调整导航顺序；模块管理自身不可停用。
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_modules') {
        $manifestFile = $MODULE_DIR . '/manifest.php';
        $all = @require $manifestFile;
        if (!is_array($all)) {
            $error = '模块清单读取失败';
            return;
        }
        $order = $_POST['order'] ?? [];
        $enabled = $_POST['enabled'] ?? [];
        if (!is_array($order)) $order = [];
        if (!is_array($enabled)) $enabled = [];
        $enabled = array_values(array_filter(array_map('strval', $enabled)));
        $byKey = [];
        foreach ($all as $m) {
            if (is_array($m) && !empty($m['key'])) $byKey[$m['key']] = $m;
        }
        // 按提交的排序数字排序
        $orderMap = [];
        foreach ($order as $k => $num) {
            $k = strval($k);
            if (isset($byKey[$k])) $orderMap[$k] = (int)$num;
        }
        asort($orderMap);
        $new = [];
        foreach ($orderMap as $k => $num) {
            $m = $byKey[$k];
            $m['enabled'] = in_array($k, $enabled, true);
            $new[] = $m;
            unset($byKey[$k]);
        }
        // 未提交排序的模块（如新注册）追加到末尾，保持原启用状态
        foreach ($byKey as $k => $m) {
            $m['enabled'] = in_array($k, $enabled, true);
            $new[] = $m;
        }
        // 模块管理自身强制启用，避免把自己关掉后无法进入
        foreach ($new as $i => $m) {
            if ($m['key'] === 'modules') $new[$i]['enabled'] = true;
        }
        $code = "<?php\n/**\n * 模块清单（manifest）——后台模块化配置中心\n *\n * 修改方式：\n *   1. 调整顺序 = 调整后台底部导航顺序（数组从上到下）\n *   2. enabled=false = 停用该模块（导航隐藏、路由不再加载）\n *   3. 新增模块：新建 modules/xxx.php（参照现有模块双模式结构）后在数组中注册\n */\nreturn " . var_export($new, true) . ";\n";
        if (@file_put_contents($manifestFile, $code) !== false) {
            $message = '模块配置已保存';
        } else {
            $error = '写入失败，请检查文件权限';
        }
    }
    return;
}
if (($MOD_RUN ?? '') === 'render') {
$manifestFile = $MODULE_DIR . '/manifest.php';
$allMods = @require $manifestFile;
if (!is_array($allMods)) $allMods = [];
$modCount = count($allMods);
?>
<div class="card">
    <div class="card-title">🧩 模块管理</div>
    <p style="font-size:.82em;color:var(--tl);margin-bottom:14px">启用/停用后台功能模块、调整导航顺序。保存后立即生效；「模块管理」自身不可停用。</p>
    <form method="post" action="manage.php?tab=modules">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="act" value="save_modules">
        <?php $i = 1; foreach ($allMods as $m): if (!is_array($m) || empty($m['key'])) continue; ?>
        <div class="list-item" style="align-items:center">
            <span style="font-size:1.4em;width:34px;text-align:center"><?php echo htmlspecialchars((string)($m['icon'] ?? '📦')); ?></span>
            <div class="item-info">
                <div class="item-title"><?php echo htmlspecialchars((string)($m['label'] ?? $m['key'])); ?>
                    <span class="item-meta"><?php echo htmlspecialchars($m['key']); ?></span>
                </div>
                <div class="item-meta">文件：<?php echo htmlspecialchars((string)($m['file'] ?? '-')); ?> · 操作：<?php echo htmlspecialchars(implode(', ', (array)($m['acts'] ?? []))); ?></div>
            </div>
            <input type="number" name="order[<?php echo htmlspecialchars($m['key']); ?>]" value="<?php echo $i++; ?>" min="1" max="<?php echo max(1, $modCount); ?>" class="neo" style="width:64px;text-align:center;padding:8px 6px">
            <?php if ($m['key'] === 'modules'): ?>
            <label class="switch"><input type="checkbox" checked disabled><span class="slider"></span></label>
            <?php else: ?>
            <label class="switch"><input type="checkbox" name="enabled[]" value="<?php echo htmlspecialchars($m['key']); ?>" <?php echo !empty($m['enabled']) ? 'checked' : ''; ?>><span class="slider"></span></label>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="btn-group">
            <button type="submit" class="btn primary">💾 保存配置</button>
        </div>
    </form>
</div>
<?php
}
