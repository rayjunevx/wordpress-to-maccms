<?php
/**
 * Plugin Name: 自动采集视频发布（多 API 版）
 * Description: 支持保存多个 API，自定义名称，采集时选择使用哪个 API。
 * Version: 1.7
 * Author: Ray
 */

if (!defined('ABSPATH')) exit;

// 后台菜单
add_action('admin_menu', function() {
    add_menu_page('视频采集', '视频采集', 'manage_options', 'vod-collector', 'vod_collector_page', 'dashicons-video-alt3');
});

// 插件页面
function vod_collector_page() {
    // 处理 API 保存/删除
    if (isset($_POST['add_api_name']) && isset($_POST['add_api_url'])) {
        $apis = get_option('vod_collector_apis', []);
        $name = sanitize_text_field($_POST['add_api_name']);
$raw_url = sanitize_text_field($_POST['add_api_url']);

if (substr($raw_url, -1) === '/') {
    // 最后是 /，保留原样
    $url = $raw_url;
} else {
    // 最后没有 /，只保留到最后一个 /
    $pos = strrpos($raw_url, '/');
    if ($pos !== false) {
        $url = substr($raw_url, 0, $pos + 1); // 保留最后一个 / 及之前
    } else {
        // 没有 /，直接保留原始地址 + /
        $url = $raw_url . '/';
    }
}
        $apis[$name] = $url;
        update_option('vod_collector_apis', $apis);
        echo '<div class="updated"><p>API 已保存！</p></div>';
    }

    if (isset($_POST['delete_api'])) {
        $apis = get_option('vod_collector_apis', []);
        $del_name = sanitize_text_field($_POST['delete_api']);
        if (isset($apis[$del_name])) {
            unset($apis[$del_name]);
            update_option('vod_collector_apis', $apis);
            echo '<div class="updated"><p>API 已删除！</p></div>';
        }
    }

    // 保存采集设置
    if (isset($_POST['vod_start_pg'])) {
        update_option('vod_collector_last_page', intval($_POST['vod_start_pg']));
        update_option('vod_collector_interval', intval($_POST['vod_interval']));
        update_option('vod_collector_selected_api', sanitize_text_field($_POST['vod_selected_api']));
        echo '<div class="updated"><p>设置已保存！</p></div>';
    }

    // 自动采集控制
    if (isset($_POST['vod_auto_start'])) {
        update_option('vod_collector_auto', 1);
        echo '<div class="updated"><p>自动采集已开启！</p></div>';
    } elseif (isset($_POST['vod_auto_stop'])) {
        update_option('vod_collector_auto', 0);
        echo '<div class="updated"><p>自动采集已暂停！</p></div>';
    }

    $apis = get_option('vod_collector_apis', []);
    $start_pg = get_option('vod_collector_last_page', 1);
    $auto_status = get_option('vod_collector_auto', 0);
    $interval = get_option('vod_collector_interval', 3);
    $selected_api = get_option('vod_collector_selected_api', key($apis) ?? '');

    ?>
    <div class="wrap">
        <h1>视频采集设置（多 API 版）</h1>

        <!-- 管理 API -->
        <h2>API 管理</h2>
        <form method="post" style="margin-bottom:15px;">
            <table class="form-table">
                <tr>
                    <th>API 名称</th>
                    <td><input type="text" name="add_api_name" required></td>
                </tr>
                <tr>
                    <th>API URL</th>
                    <td><input type="text" name="add_api_url" size="50" required> （保留最后的 /）</td>
                </tr>
            </table>
            <p><input type="submit" class="button-primary" value="添加 API"></p>
        </form>

        <table class="widefat">
            <thead>
                <tr>
                    <th>API 名称</th>
                    <th>API URL</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apis as $name => $url): ?>
                    <tr>
                        <td><?php echo esc_html($name); ?></td>
                        <td><?php echo esc_url($url); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="delete_api" value="<?php echo esc_attr($name); ?>">
                                <input type="submit" class="button-link" value="删除" onclick="return confirm('确定删除？');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <hr>

        <!-- 采集设置 -->
        <h2>采集设置</h2>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>选择 API</th>
                    <td>
                        <select name="vod_selected_api">
                            <?php foreach ($apis as $name => $url): ?>
                                <option value="<?php echo esc_attr($name); ?>" <?php selected($selected_api, $name); ?>>
                                    <?php echo esc_html($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>起始页码</th>
                    <td><input type="number" name="vod_start_pg" value="<?php echo esc_attr($start_pg); ?>"></td>
                </tr>
                <tr>
                    <th>自动采集间隔（秒）</th>
                    <td><input type="number" name="vod_interval" value="<?php echo esc_attr($interval); ?>" min="1"></td>
                </tr>
            </table>
            <p><input type="submit" class="button-primary" value="保存设置"></p>
        </form>

        <!-- 立即采集 / 自动采集 -->
        <form method="post" style="margin-top:15px;">
            <p>
                <input type="submit" name="vod_collect_now" class="button-primary" value="立即采集下一页">
                <?php if($auto_status): ?>
                    <input type="submit" name="vod_auto_stop" class="button-secondary" value="暂停自动采集">
                <?php else: ?>
                    <input type="submit" name="vod_auto_start" class="button-secondary" value="开启自动采集">
                <?php endif; ?>
            </p>
        </form>

        <div id="vod-collector-log" style="margin-top:20px; max-height:400px; overflow:auto; border:1px solid #ddd; padding:10px; background:#f9f9f9;">
            <p>当前页码：<?php echo esc_html($start_pg); ?></p>
            <?php
            $logs = [];

            if (isset($_POST['vod_collect_now']) || $auto_status) {
                $current_pg = intval(get_option('vod_collector_last_page', 1));
                $logs = vod_collect_page($current_pg, $selected_api);
                update_option('vod_collector_last_page', $current_pg + 1);

                if($auto_status){
                    echo "<script>setTimeout(function(){ location.reload(); }, ".($interval*1000).");</script>";
                }
            }

            $logs = array_slice($logs, -20);
            foreach ($logs as $log) {
                $color = strpos($log, '采集成功') !== false ? 'green' : (strpos($log, '已采集') !== false ? 'red' : 'black');
                echo '<p style="color:'.$color.';">'.esc_html($log).'</p>';
            }
            ?>
        </div>
    </div>
    <?php
}

// 分页采集函数（增加选择 API 参数）
function vod_collect_page($pg = 1, $api_name = '') {
    $logs = [];
    $apis = get_option('vod_collector_apis', []);
    if (empty($apis[$api_name])) {
        $logs[] = "未选择或未配置 API";
        return $logs;
    }

    $api_url = $apis[$api_name];
    $url = $api_url . '?ac=videolist&t=&pg=' . $pg . '&h=10000&ids=&wd=';

    $response = wp_remote_get($url);
    if (is_wp_error($response)) {
        $logs[] = '请求 API 失败';
        return $logs;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (empty($data) || $data['code'] != 1 || empty($data['list'])) {
        $logs[] = '未获取到数据';
        return $logs;
    }

    foreach ($data['list'] as $vod) {
        $title = sanitize_text_field($vod['vod_name']);

        $exists = get_page_by_title($title, OBJECT, 'post');
        if ($exists) {
            $logs[] = "$title —— 已采集";
            continue;
        }

        $cat_name = sanitize_text_field($vod['type_name']);
        $cat_id = term_exists($cat_name, 'category');
        if (!$cat_id) {
            $cat_id = wp_insert_term($cat_name, 'category');
            $cat_id = $cat_id['term_id'];
        } else {
            $cat_id = $cat_id['term_id'];
        }

        $content = '<div class="vod-collector-container" style="max-width:800px;margin:20px auto;padding:20px;background:#fff;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">';
        $content .= '<h2 style="margin-top:0;color:#222;">' . esc_html($vod['vod_name']) . '</h2>';
        if (!empty($vod['vod_pic'])) {
            $content .= '<div style="text-align:center;margin-bottom:15px;"><img src="' . esc_url($vod['vod_pic']) . '" alt="' . esc_attr($vod['vod_name']) . '" style="max-width:100%;border-radius:8px;"></div>';
        }
        $content .= '<p><strong>简介：</strong>' . esc_html($vod['vod_blurb']) . '</p>';
        $content .= '<p><strong>备注：</strong>' . esc_html($vod['vod_remarks']) . '</p>';
        $content .= '<p><strong>发布日期：</strong>' . esc_html($vod['vod_pubdate']) . '</p>';
        $content .= '<p><strong>时长：</strong>' . esc_html($vod['vod_duration']) . '</p>';

        if (!empty($vod['vod_play_url'])) {
            $urls = explode('$$$', $vod['vod_play_url']);
            foreach ($urls as $u) {
                if (strpos($u, '$') === false) continue;
                list($label, $link) = explode('$', $u);
                $link = trim($link);

                if (stripos($link, '.m3u8') !== false) {
                    $content .= '<div class="vod-player" style="margin:15px 0;text-align:center;">
                        <video controls style="max-width:100%;border-radius:8px;" src="' . esc_url($link) . '"></video>
                    </div>';
                } else {
                    $content .= '<p style="margin:10px 0;padding:10px;background:#f0f0f5;border-left:4px solid #0073aa;border-radius:6px;">
                        <strong>备用播放地址（' . esc_html($label) . '）:</strong> 
                        <a href="' . esc_url($link) . '" target="_blank" style="color:#0073aa;text-decoration:underline;">点击播放</a>
                    </p>';
                }
            }
        }

        $content .= '</div>';

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'publish',
            'post_category' => [$cat_id],
        ]);

        if ($post_id && !empty($vod['vod_pic'])) {
            update_post_meta($post_id, 'vod_featured_image', esc_url($vod['vod_pic']));
        }

        $logs[] = "$title —— 采集成功";
    }

    return $logs;
}
