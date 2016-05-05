<?php

class WikiInfoGetter
{
    public static function query($name)
    {
        $target = __DIR__  . '/cache/' . $name . '.html';
        if (file_exists($target)) {
            $content = file_get_contents($target);
        } else {
            $url = 'https://zh.wikipedia.org/zh-tw/' . urlencode($name);
            error_log($name . ' ' . $url);
            $content = file_get_contents($url);
            file_put_contents($target, $content);
        }

        if (!$content) {
            throw new Exception('404');
        }
        if (strpos($content, '羅列了有相同或相近的標題')) {
            die ($name . ' 消歧義');
        }
        $doc = new DOMDocument;
        $doc->loadHTML($content);

        $info = new StdClass;
        foreach ($doc->getElementsByTagName('div') as $div_dom) {
            if ($div_dom->nodeValue == '個人資料') {
                $tr_dom = $div_dom->parentNode->parentNode;
                $next_tr_dom = $tr_dom;
                while ($next_tr_dom = $next_tr_dom->nextSibling) {
                    if ($next_tr_dom->getAttribute('colspan') == 2) {
                        break;
                    }
                    $th_dom = $next_tr_dom->getElementsByTagName('th')->item(0);
                    if ($th_dom and $th_dom->getAttribute('colspan') == 2) {
                        break;
                    }
                    $td_dom = $next_tr_dom->getElementsByTagName('td')->item(0);
                    if ($td_dom->getAttribute('colspan') == 2) {
                        break;
                    }

                    $info->{$th_dom->nodeValue} = trim($td_dom->nodeValue);
                }
            } elseif (in_array($div_dom->nodeValue, array('經歷', '學歷'))) {
                $tr_dom = $div_dom->parentNode->parentNode;
                $next_tr_dom = $tr_dom->nextSibling;

                $list = array();
                error_log($th_dom->nodeValue);
                foreach ($next_tr_dom->getElementsByTagName('li') as $li_dom) {
                    $t = trim($li_dom->nodeValue);

                    $list[] = $t;
                }
                $info->{trim($div_dom->nodeValue)} = $list;
            }
        }
        foreach ($doc->getElementsByTagName('th') as $th_dom) {
            if (in_array($th_dom->nodeValue, array('經歷', '學歷')) and $th_dom->getAttribute('colspan') == 2) {
                $tr_dom = $th_dom->parentNode;
                $next_tr_dom = $tr_dom->nextSibling;

                $list = array();
                error_log($th_dom->nodeValue);
                foreach ($next_tr_dom->getElementsByTagName('li') as $li_dom) {
                    $t = trim($li_dom->nodeValue);

                    $list[] = $t;
                }
                $info->{$th_dom->nodeValue} = $list;
            }
        }

        while (!property_exists($info, '出生')) {
            foreach ($doc->getElementsByTagName('span') as $span_dom) {
                if ($span_dom->getAttribute('class') == 'bday') {
                    list($y, $m, $d) = array_map('intval', explode('-', $span_dom->nodeValue));
                    $info->{'出生'} = "{$y}年{$m}月{$d}日";
                    break 2;
                }
            }

            $name = $doc->getElementById('firstHeading')->nodeValue;
            $name = preg_replace('# \([^)]*\)#', '', $name);

            $text = '';
            foreach ($doc->getElementsByTagName('b') as $b_dom) {
                if ($b_dom->childNodes->item(0)->nodeName == '#text' and $b_dom->nodeValue == $name or $b_dom->nodeValue == $name . '博士') {
                    $text = '';
                    $d = $b_dom;
                    for ($i = 0; $i < 100 and $d = $d->nextSibling; $i ++) {
                        $text .= trim($d->nodeValue);
                        $d = $d->nextSibling;
                    }
                    break;
                }
            }
            $text = mb_substr($text, 0, 50);

            if (preg_match('#\d+年\d*月?\d*日?#u', $text, $matches)) {
                $info->{'出生'} = $matches[0];
                break;
            }

            if (preG_match('#(\d{4}年)出生#', $content, $matches)) {
                $info->{'出生'} = $matches[1];
                break;
            }

            break;
        }

        while (!property_exists($info, '性別')) {
            foreach ($doc->getElementsByTagName('th') as $th_dom) {
                if ($th_dom->nodeValue == '性別') {
                    $info->{'性別'} = trim($th_dom->nextSibling->nextSibling->nodeValue);
                    break 2;
                }
            }

            break;
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
            throw new Exception($failed[$name]);
        }
        $info = $query_and_cache($name);
        if (property_exists($info, '出生') and preg_match('#\d{4}年\d*月?\d*日?#u', $info->{'出生'}, $matches)) {
            $birth = $matches[0];
        }
        if (property_exists($info, '性別')) {
            $gender = $info->{'性別'};
            if (!array_key_exists($gender, $gender_map)) {
                die ("{$name} 的性別是 {$gender}");
            }
            $gender = $gender_map[$gender];
        }
    } catch (Exception $e) {
        $failed[$name] = $e->getMessage();
        file_put_contents('failed', json_encode($failed, JSON_UNESCAPED_UNICODE));
    }
    if (array_key_exists(4, $rows)) {
        if ($gender and $gender != $rows[4]) {
            die ("{$name} 的性別不同步 {$gender}");
        }
        $zhengwei_gender[$name] = $rows[4];
        $gender = $rows[4];
    }

    if (!$gender and array_key_exists($name, $zhengwei_gender)) {
        $gender = $zhengwei_gender[$name];
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
