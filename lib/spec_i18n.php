<?php

/** Map Chinese spec labels from zhangjiquan.com to English. */
function hh_spec_key_map_en()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = array(
        '内存' => 'RAM',
        '别名' => 'Alias',
        '名称' => 'Name',
        '品牌' => 'Brand',
        '存储' => 'Storage',
        '振动' => 'Rumble',
        '散热' => 'Cooling',
        '电池' => 'Battery',
        '重量' => 'Weight',
        'CPU主频' => 'CPU Clock',
        '处理器' => 'SoC / Processor',
        '屏幕PPI' => 'Screen PPI',
        '扬声器' => 'Speakers',
        '连接性' => 'Connectivity',
        '价格区间' => 'Price Range',
        '发布时间' => 'Release Date',
        '外壳材质' => 'Shell Material',
        '外观尺寸' => 'Dimensions',
        '屏幕尺寸' => 'Screen Size',
        '屏幕比例' => 'Aspect Ratio',
        '屏幕类型' => 'Screen Type',
        '屏幕色域' => 'Color Gamut',
        '握持类型' => 'Orientation',
        '操作系统' => 'OS',
        '机身颜色' => 'Colors',
        '视频输出' => 'Video Output',
        '起售价格' => 'Launch Price',
        '音频输出' => 'Audio Output',
        'GPU核心频率' => 'GPU Clock',
        '其他传感器' => 'Other Sensors',
        '屏幕分辨率' => 'Resolution',
        '屏幕刷新率' => 'Refresh Rate',
        '屏幕最高亮度' => 'Peak Brightness',
        'CPU核心和线程数' => 'CPU Cores / Threads',
        '模拟器游戏支持' => 'Emulator Support',
        'CPU' => 'CPU',
        'GPU' => 'GPU',
    );
    return $map;
}

function hh_spec_key_en($zhKey)
{
    $map = hh_spec_key_map_en();
    return isset($map[$zhKey]) ? $map[$zhKey] : $zhKey;
}

function hh_spec_value_en($value)
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return $value;
    }

    $exact = array(
        '有' => 'Yes',
        '无' => 'No',
        '竖版' => 'Portrait',
        '横版' => 'Landscape',
        '塑料或金属' => 'Plastic or metal',
        '塑料' => 'Plastic',
        '金属' => 'Metal',
        '单扬声器' => 'Mono speaker',
        '双扬声器' => 'Stereo speakers',
        '安卓' => 'Android',
        'Linux' => 'Linux',
        'Windows' => 'Windows',
        'IPS 触摸屏' => 'IPS touchscreen',
        'OLED 触摸屏' => 'OLED touchscreen',
        '黑色银色' => 'Black, Silver',
        '黑色' => 'Black',
        '银色' => 'Silver',
        '白色' => 'White',
    );
    if (isset($exact[$value])) {
        return $exact[$value];
    }

    $v = $value;
    $v = str_replace('蓝牙', 'Bluetooth', $v);
    $v = str_replace('虎贲', 'Unisoc', $v);
    $v = str_replace('联发科', 'MediaTek', $v);
    $v = str_replace('高通', 'Qualcomm', $v);
    $v = str_replace('瑞芯微', 'Rockchip', $v);
    $v = preg_replace('/多核得分[：:]\s*/u', 'multicore score: ', $v);
    $v = preg_replace('/内置\s*/u', 'Internal ', $v);
    $v = preg_replace('/外置/u', 'External ', $v);
    $v = preg_replace('/TF卡插槽/u', 'TF card slot', $v);
    $v = preg_replace('/(\d+(?:\.\d+)?)\s*核(\d+)线程/u', '$1 cores / $2 threads', $v);
    $v = preg_replace('/(\d+(?:\.\d+)?)\s*核/u', '$1 cores', $v);
    $v = preg_replace('/(\d+(?:\.\d+)?)\s*英寸/u', '$1-inch', $v);
    $v = preg_replace('/(\d+(?:\.\d+)?)\s*克/u', '$1 g', $v);
    $v = preg_replace('/(\d+(?:\.\d+)?)\s*毫安(?:时)?/u', '$1 mAh', $v);
    $v = preg_replace('/音频输出/u', 'audio output', $v);
    $v = preg_replace('/触摸屏/u', 'touchscreen', $v);
    $v = preg_replace('/，/u', ', ', $v);
    $v = preg_replace('/、/u', ', ', $v);
    $v = str_replace('￥', '¥', $v);
    $v = preg_replace('/\s+/u', ' ', $v);

    return trim($v);
}

function hh_specs_localize($specs, $locale = 'en')
{
    if (!is_array($specs) || $locale === 'zh') {
        return is_array($specs) ? $specs : array();
    }
    $out = array();
    foreach ($specs as $k => $v) {
        if ($v === '' || $v === '-') {
            continue;
        }
        $out[hh_spec_key_en((string) $k)] = hh_spec_value_en($v);
    }
    return $out;
}
