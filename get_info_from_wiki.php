<?php

class Error extends Exception
{
}

class WikiInfoGetter
{
    public static function query($name)
    {
        $target = __DIR__  . '/cache/' . $name . '.json';
        $url = 'https://zh.wikipedia.org/zh-tw/' . urlencode($name);
        if (file_exists($target)) {
            $content = file_get_contents($target);
        } else {
            error_log($name . ' ' . $url);
            $content = file_get_contents($url);
            if (!$content) {
                throw new Error('404');
            }
            if (strpos($content, '羅列了有相同或相近的標題')) {
                throw new Exception($name . ' 消歧義');
            }
            if (!preg_match('#"wgWikibaseItemId":"([^"]*)"#', $content, $matches)) {
                throw new Error("找不到 wiki data id");
            }
            $content = file_get_Contents("https://www.wikidata.org/wiki/Special:EntityData/{$matches[1]}.json?v=" . time());

            file_put_contents($target, $content);
        }
        $obj = json_decode($content);
        $data = array_values(get_object_vars($obj->entities))[0];
        $info = new StdClass;
        if (property_exists($data->claims, 'P21')) { // 性別
            if ('6581097' == $data->claims->P21[0]->mainsnak->datavalue->value->{'numeric-id'}) {
                $info->{'性別'} = '男';
            } elseif ('6581072' == $data->claims->P21[0]->mainsnak->datavalue->value->{'numeric-id'}) {
                $info->{'性別'} = '女';
            } else {
                print_r($data->claims->P21);
                echo "https://www.wikidata.org/wiki/{$data->id}\n";
                echo "$url\n";
                echo $target . "\n";
                exit;
            }
        } else {
            echo "$url\n";
            echo $target . "\n";
            throw new Exception("找不到性別");
        }

        if (property_exists($data->claims, 'P569')) { // 出生
            $only_year_names = array(
                '劉航琛',
                '萬鴻圖',
                '董文琦',
                '王師曾_(涪陵)',
                '余井塘',
                '田炯錦',
                '蔣勻田',
                '陳雪屏',
                '林金生',
                '張劍寒',
                '陳錦煌',
                '張有惠',
                '葉國興',
                '林萬億',
                '葉欣誠',
                '吳政忠',
                '陳美伶',
                '鄭洪年',
                '甘乃光',
                '李惟果',
                '賈景德',
                '陳慶瑜',
                '端木愷',
                '彭昭賢',
                '洪蘭友',
                '王德溥',
                '袁守謙',
                '黃杰_(將軍)',
                '陳大慶',
                '劉攻芸',
                '呂有文',
                '劉維熾',
                '鄭道儒',
                '陶聲洋',
                '宗才怡',
                '沈榮津',
                '端木傑',
                '鄭水枝',
                '顏春輝',
                '王金茂',
                '蔣丙煌',
                '林一平',
                '施能傑',
                '林祖嘉',
                '李金龍_(園藝)',
                '石青陽',
                '黃慕松',
                '許世英',
                '張祖恩',
                '張國龍',
                '陳重信',
                '趙聚鈺',
                '張國英',
                '王正誼_(中華民國)',
                '陳清秀',
                '吳泰成',
                '劉義周',
                '陳英鈐',
                '蘇蘅',
                '詹婷怡',
                '顧孟餘',
            );
            foreach ($data->claims->P569 as $claim) {
                $datavalue = $claim->mainsnak->datavalue;
                if ((in_array($name, $only_year_names) or $datavalue->value->precision == 11) and preg_match('#\+(\d+)-(\d+)-(\d+)T00:00:00Z#', $datavalue->value->time, $matches)) {
                    if ($datavalue->value->precision == 11) {
                        $info->{'出生'} = $matches[1] . '年' . intval($matches[2]) . '月' . intval($matches[3]) . '日';
                    } elseif ($datavalue->value->precision == 9) {
                        $info->{'出生'} = $matches[1] . '年';
                    } elseif ($datavalue->value->precision == 10) {
                        $info->{'出生'} = $matches[1] . '年' . intval($matches[2]) . '月';
                    } else {
                        print_r($datavalue);
                        throw new Exception('precision');
                    }
                    break;
                }
            }
            if (!$info->{'出生'}) {
                print_r($data->claims->P569);
                echo "https://www.wikidata.org/wiki/{$data->id}\n";
                echo "$url\n";
                echo $target . "\n";
                exit;
            }
        } else {
            $skip_birth = array(
                '吳宏謀',
                '郝鳳鳴',
                '陳時中_(政治人物)',
            );
            if (!in_array($name, $skip_birth)) {
                echo "$url\n";
                echo $target . "\n";
                throw new Exception("找不到出生");
            }
        }
       

        return $info;
    }
}

$query_and_cache = function($name){
    $map = array(
        '黃少谷' => '黃少谷_(政治)',
        '王世傑' => '王世杰_(中華民國)',
        '王世杰' => '王世杰_(中華民國)',
        '王昭明' => '王昭明_(台灣)',
        '李模' => false,
        '孫震' => '孫震_(臺灣學政界人物)',
        '陳健民' => '陳健民_(立法委員)',
        '傅立葉' => false,
        '李大維' => '李大維_(外交官)',
        '謝志偉' => '謝志偉_(臺灣)',
        '黃杰' => '黃杰_(將軍)',
        '楊念祖' => '楊念祖_(臺灣學政界人物)',
        '李傑' => '李傑_(臺灣)',
        '王徵' => false,
        '馮燕' => false,
        '嚴明' => '嚴明_(臺灣)',
        '吳京' => '吳京_(學者)',
        '王志剛' => '王志剛_(中華民國)',
        '沈怡' => '沈怡_(官員)',
        '李金龍' => '李金龍_(園藝)',
        '陳武雄' => '陳武雄_(農業專家)',
        '陳明仁' => false,
        '陳冲' => '陳冲_(臺灣)',
        '陳建年' => '陳建年_(政治人物)',
        '王正誼' => '王正誼_(中華民國)',
        '瓦歷斯·貝林（蔡貴聰）' => '蔡貴聰',
        '夷將·拔路兒（劉文雄）' => '夷將·拔路兒',
        '王師曾' => '王師曾_(涪陵)',
        '王郡' => false,
        '郭澄' => false,
        '陳德華' => false,
        '李建中' => false,
        '姚立德' => false,
        '王秀紅' => false,
        '王仁宏' => false,
        '陳時中' => '陳時中_(政治人物)',
        '陳豫' => false,
        '韋端' => '韋伯韜',
    );
    if (array_key_exists($name, $map)) {
        $name = $map[$name];
    }
    if ($name === false) {
        return new StdClass;
    }

    $result = WikiInfoGetter::query($name);
    return $result;
};

$failed = json_decode(file_get_contents('failed'), true) ?: array();

$fp = fopen('sweetcow.csv', 'r');
fgetcsv($fp);
$other_gender = array();
while ($rows = fgetcsv($fp)) {
    //$other_gender[$rows[1]] = $rows[5];
}

$fp = popen('cat 行政院-政務委員.csv 行政院-閣員.csv', 'r');
$output = fopen('php://output', 'w');
fputcsv($output, array(
    '職稱','姓名','到職','卸任','出生','性別'
));
$gender_map = array('男性' => 'M', '男' => 'M', '女性' => 'F', '女' => 'F');
$zhengwei_gender = array();
while ($rows = fgetcsv($fp)) {
    list($title, $name, $start, $end) = $rows;
    if ($title == '職稱') {
        continue;
    }

    $name = preg_replace('#（二次）#', '', $name);
    $name = str_replace(' ', '', $name);
    $name = str_replace('　', '', $name);
    $name = trim($name);

    $birth = '';
    $gender = '';

    try {
        if (array_key_exists($name, $failed)) {
            throw new Error($failed[$name]);
        }
        $info = $query_and_cache($name);
        if (property_exists($info, '出生') and preg_match('#\d{4}年\d*月?\d*日?#u', $info->{'出生'}, $matches)) {
            $birth = $matches[0];
        }
        if (property_exists($info, '性別')) {
            $gender = $info->{'性別'};
            if (!array_key_exists($gender, $gender_map)) {
                throw new Exception("{$name} 的性別是 {$gender}");
            }
            $gender = $gender_map[$gender];
        }
    } catch (Error $e) {
        $failed[$name] = $e->getMessage();
        file_put_contents('failed', json_encode($failed, JSON_UNESCAPED_UNICODE));
    }
    if (array_key_exists(4, $rows)) {
        if (!$rows[4] and $gender) {
            $rows[4] = $gender;
        }
        if ($gender and $gender != $rows[4]) {
            throw new Exception("{$name} 的性別不同步 {$gender} != {$rows[4]}");
        }
        $zhengwei_gender[$name] = $rows[4];
        $gender = $rows[4];
    }

    if (!$gender and array_key_exists($name, $zhengwei_gender)) {
        $gender = $zhengwei_gender[$name];
    }
    if (!$gender and array_key_exists($name, $other_gender)) {
        $gender = $other_gender[$name];
    }
    if (!preg_match('#^\d+年\d*月?\d*日?#u', $start, $matches)) {
        $start = '';
    } else {
        $start = $matches[0];
    }
    if (!preg_match('#^\d+年\d*月?\d*日?#u', $end, $matches)) {
        $end = '';
    } else {
        $end = $matches[0];
    }
    fputcsv($output, array(
        $title, $name, $start, $end, $birth, $gender,
    ));
}
