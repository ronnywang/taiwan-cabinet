<?php

class MyError extends Exception
{
}

class WikiInfoGetter
{
    public static function query($name, $ori_name = null, $birth_year = null)
    {
        $target = __DIR__  . '/cache/' . $name . '.json';
        $url = 'https://zh.wikipedia.org/zh-tw/' . urlencode($name);
        if (file_exists($target)) {
            $content = file_get_contents($target);
        } else {
            error_log("geting {$name} ({$ori_name}) $url");
            $content = file_get_contents($url);
            if (!$content) {
                throw new MyError('404');
            }
            if (strpos($content, '羅列了有相同或相近的標題')) {
                throw new Exception($name . ' 消歧義');
            }
            if (!preg_match('#"wgWikibaseItemId":"([^"]*)"#', $content, $matches)) {
                throw new MyError("找不到 wiki data id");
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
            } elseif ('48270' == $data->claims->P21[0]->mainsnak->datavalue->value->{'numeric-id'}) {
                $info->{'性別'} = '無'; // au
            } else {
                error_log($data->claims->P21);
                error_log("https://www.wikidata.org/wiki/{$data->id}");
                error_log($url);
                error_log($target);
            }
        } else {
            error_log($url);
            error_log($target);
            error_log("{$name} 找不到性別");
        }

        if (property_exists($data->claims, 'P569')) { // 出生
            $only_year_names = array();
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

            if (!($info->{'出生'} ?? false)) {
                /*error_log("找不到出生");
                error_log(json_encode($data->claims->P569));
                error_log("https://www.wikidata.org/wiki/{$data->id}");
                error_log($url);
                error_log($target);
                exit;*/
            }
        }
        if (!($info->{'出生'} ?? false) and $birth_year) {
            $info->{'出生'} = $birth_year . '年';
        }
       

        return $info;
    }
}

$query_and_cache = function($name, $ori_name = null, $birth_year = null){
    $map = array(
        '王世傑' => '王世杰_(1891年)',
        '王世杰' => '王世杰_(1891年)',
        '胡志強' => '胡志強_(1948年)',
        '王昭明' => '王昭明_(民國)',
        '李模' => false,
        '孫震' => '孫震_(臺灣學政界人物)',
        '陳健民' => '陳健民_(立法委員)',
        '傅立葉' => false,
        '張國英' => '張國英_(中華民國將領)',
        '李文忠' => '李文忠_(臺灣)',
        '李大維' => '李大維_(外交官)',
        '王徵' => false, // 1948 年生
        '馮燕' => false,
        '吳京' => '吳京_(1934年)',
        '王志剛' => '王志剛_(中華民國)',
        '沈怡' => '沈怡_(政治人物)',
        '李金龍' => '李金龍_(園藝)',
        '陳武雄' => '陳武雄_(農業專家)',
        '陳志清' => '陳志清_(1952年)',
        '陳明仁' => false,
        '陳冲' => '陳冲_(臺灣)',
        '張富美' => '張富美_(政治人物)',
        '陳建年' => '陳建年_(政治人物)',
        '王正誼' => '王正誼_(中華民國)',
        '楊金龍' => '楊金龍_(金融人物)',
        '李建中' => false,
        '潘世偉' => '潘世偉_(1955年)',
        '雷震' => false,
        '何佩珊' => '何佩珊_(台灣)',
        '陳良' => '陳良_(1896年)',
        '張明哲' => '張明哲_(1914年)',
    );
    if (array_key_exists($name, $map)) {
        $name = $map[$name];
    }
    if ($name === false) {
        return new StdClass;
    }

    if (!$name) {
        throw new MyError("找不到名字 : $ori_name");
    }

    $result = WikiInfoGetter::query($name, $ori_name, $birth_year);
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
$gender_map = array('男性' => 'M', '男' => 'M', '女性' => 'F', '女' => 'F', '無' => 'N');
$zhengwei_gender = array();
while ($rows = fgetcsv($fp)) {
    list($title, $name, $start, $end) = $rows;
    if ($title == '職稱') {
        continue;
    }

    $ori_name = '' . $name;
    if (!$ori_name) {
        throw new Exception("找不到名字 : $ori_name");
    }
    $name = preg_replace('#（二次）#', '', $name);
    $name = str_replace(' ', '', $name);
    $name = str_replace('　', '', $name);
    $name = trim($name);

    $birth = '';
    $gender = '';

    try {
        if (array_key_exists($name, $failed)) {
            throw new MyError($failed[$name]);
        }
        $name = str_replace('（', '(', $name);
        $name = str_replace('）', ')', $name);
        $birth_year = null;
        if (preg_match('#^([^()]+)\((\d+)–(\d+)?\)$#u', $name, $matches)) {
            $name = $matches[1];
            $birth_year = $matches[2] ?? null;
        }
        if (!$name) {
            throw new Exception("找不到名字 : $ori_name");
        }
        $info = $query_and_cache($name, $ori_name, $birth_year);
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
    } catch (MyError $e) {
        $failed[$name] = $e->getMessage();
        file_put_contents('failed', json_encode($failed, JSON_UNESCAPED_UNICODE));
    }
    if ($name === '') {
        throw new Exception("找不到名字 : $ori_name");
    }
    if (array_key_exists(4, $rows)) {
        if (!$rows[4] and $gender) {
            $rows[4] = $gender;
        }
        if ($gender == 'T' and $rows[4] != 'T') {
            $gender = $rows[4];
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
