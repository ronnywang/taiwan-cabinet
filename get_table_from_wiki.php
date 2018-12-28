<?php

date_default_timezone_set('Asia/Taipei');

class WikiTableParser
{
    public function getTable($name)
    {
        $target = __DIR__ . '/cache/' . $name . '.json';
        if (file_exists($target)) {
            $content = file_get_contents($target);
        } else {
            $url = 'https://zh.wikipedia.org/zh-tw/' . urlencode($name);
            $content = file_get_contents($url);
            file_put_contents($target, $content);
        }
        $doc = new DOMDocument;
        @$doc->loadHTML($content);

        $tables = array();
        foreach ($doc->getElementsByTagName('table') as $table_dom) {
            if (!in_array('wikitable', explode(' ', $table_dom->getAttribute('class')))) {
                continue;
            }

            $records = array();
            $tr_doms = $table_dom->getElementsByTagName('tr');
            $columns = array();
            $group = null;
            $span_lines = array();
            foreach ($tr_doms as $tr_dom) {
                // 只有一個 td 沒其他東西表示表頭空白可以不理他
                if ($tr_dom->getElementsByTagName('td')->length == 1 and $tr_dom->getElementsByTagName('th')->length == 0) {
                    continue;
                }
                if ($tr_dom->getElementsByTagName('th')->length == 1 and $tr_dom->getElementsByTagName('td')->length == 0) {
                    $group = trim($tr_dom->getElementsByTagName('th')->item(0)->nodeValue);
                    continue;
                }
                if (count($span_lines)) {
                    $span_line = array_shift($span_lines);
                } else {
                    $span_line = array();
                }

                if ($tr_dom->getElementsByTagName('td')->length == 0) {
                    $col = 0;
                    while (array_key_exists($col, $span_line)) {
                        $col ++;
                    }
                    foreach ($tr_dom->getElementsByTagName('th') as $th_dom) {
                        $colspan = 1;
                        if ($th_dom->getAttribute('colspan')) {
                            $colspan = intval($th_dom->getAttribute('colspan'));
                        }
                        for ($t = 0; $t < $colspan; $t ++) {
                            if ($th_dom->getAttribute('rowspan')) {
                                for ($i = 0; $i < $th_dom->getAttribute('rowspan') - 1; $i ++) {
                                    if (!array_key_exists($i, $span_lines)) {
                                        $span_lines[$i] = array();
                                    }
                                    $span_lines[$i][$col] = true;
                                }
                            }
                            if (!array_key_exists($col, $columns)) {
                                $columns[$col] = trim($th_dom->nodeValue);
                            } else{
                                $columns[$col] .= '/' . trim($th_dom->nodeValue);
                            }
                            $col ++;
                            while (array_key_exists($col, $span_line)) {
                                $col ++;
                            }
                        }
                    }
                } else {
                    $values = array();
                    $col = 0;
                    while (array_key_exists($col, $span_line)) {
                        $values[$col] = $span_line[$col];
                        $col ++;
                    }
                    foreach ($tr_dom->childNodes as $n) {
                        if (!in_array($n->nodeName, array('th', 'td'))) {
                            continue;
                        }
                        $v = trim($n->nodeValue);
                        if ($v == '現任') {
                            $v = '';
                        }
                        if (false !== strpos($v, '候任')) {
                            $v = '候任';
                        }
                        $v = preg_replace('#\[[^\]]*\]#', '', $v);
                        $colspan = 1;
                        if ($n->getAttribute('colspan')) {
                            $colspan = intval($n->getAttribute('colspan'));
                        }
                        for ($t = 0; $t < $colspan; $t ++) {
                            if ($n->getAttribute('rowspan')) {
                                for ($i = 0; $i < $n->getAttribute('rowspan') - 1; $i ++) {
                                    if (!array_key_exists($i, $span_lines)) {
                                        $span_lines[$i] = array();
                                    }
                                    $span_lines[$i][$col] = trim($v);
                                }
                            }
                            $values[$col] = trim($v);
                            $col ++;
                        }
                        while (array_key_exists($col, $span_line)) {
                            $values[$col] = $span_line[$col];
                            $col ++;
                        }
                    }
                    if (in_array($values[0], array('階級識別', '文職', '軍職', '警務', '關務'))) {
                        continue;
                    }
                    if (count($columns) != count($values)) {
                        vaR_dump($columns);
                        var_dump($values);
                        throw new Exception("column value failed");
                    }
                    $rows = array_combine($columns, $values);
                    
                    if (!is_null($group)) {
                        $rows['group'] = $group;
                    }
                    $records[] = $rows;
                }
            }
            $tables[] = $records;
        }
        return $tables;
    }
}
$prev_date = function($d) {
    preg_match('#(\d*)年(\d*)月(\d*)日#', $d, $matches);
    $d = mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
    $d -= 86400;
    return sprintf("%d年%d月%d日", date('Y', $d), date('m', $d), date('d', $d));
};

$output = fopen('php://output', 'w');
echo "職稱,姓名,到職,卸任\n";
$result = array();

$add_result = function($title, $name, $start, $end) use (&$result, $prev_date) {
    $result[] = array(
        $title,
        $name,
        $start,
        $end,
    );

    // 處理沒有卸任時間或是卸任時間跟到職日同一天的情況
    $pos = count($result) - 1;
    if ($pos == 0) {
        return;
    }

    if ($result[$pos - 1][0] != $result[$pos][0]) {
        // 如果職稱不一樣就不管他了
        return;
    }

    if (!$result[$pos - 1][3]) {
        $result[$pos - 1][3] = $prev_date($result[$pos][2]);
        return;
    }

    if ($result[$pos - 1][3] == $result[$pos][2] and strpos($result[$pos][2], '日')) {
        $result[$pos - 1][3] = $prev_date($result[$pos][2]);
        return;
    }
};

$k = '行政院/秘書長';
foreach (WikiTableParser::getTable('行政院秘書長') as $table) {
    foreach ($table as $record) {
        $add_result(
            $k,
            $record['姓名'],
            $record['任職期間/到任時間'],
            $record['任職期間/卸任時間']
        );
    }
}

// TODO: 行政院副秘書長

$k = '行政院/發言人';
$tables = WikiTableParser::getTable('行政院新聞傳播處');
foreach ($tables[0] as $record) {
    $add_result(
        '行政院新聞局/局長',
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}
foreach ($tables[1] as $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '內政部/部長';
$tables = WikiTableParser::getTable('中華民國內政部');
foreach ($tables[0] as $record) {
    if ('中華民國內政部部長' != $record['group']) {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '外交部/部長';
$tables = WikiTableParser::getTable('中華民國外交部');
foreach ($tables[3] as $record) {
    $add_result(
        $k,
        $record['姓　名'],
        $record['上任日期'],
        $record['離任日期']
    );
}

$k = '國防部/部長';
$tables = WikiTableParser::getTable('中華民國國防部');
foreach ($tables[9] as $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '財政部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[0] as $id => $record) {
    if ('財政部部長（行憲後）' != $record['group']) {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}


$k = '教育部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[4] as $id => $record) {
    if ('未就任' == $record['備註']) {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['上任時間'],
        $record['卸任時間']
    );
}

$k = '法務部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[5] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '經濟部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[3] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '交通部/部長';
$tables = WikiTableParser::getTable('中華民國交通部部長');
foreach ($tables[0] as $id => $record) {
    if ('行憲後' != $record['group']) {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['任職期間/到任時間'],
        $record['任職期間/卸任時間']
    );
}

$k = '勞動部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[0] as $id => $record) {
    if ('行政院勞工委員會 主任委員' == $record['group']) {
        $k = '行政院勞工委員會/主任委員';
    } else {
        $k = '勞動部/部長';
    }
        
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '衛生福利部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[0] as $id => $record) {
    if ('行政院衛生署 署長（第3次）' == $record['group']) {
        $k = '行政院衛生署/署長';
    } elseif ('衛生福利部 部長（第4次）' == $record['group']) {
        $k = '衛生福利部/部長';
    } else {
        continue;
    }

    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '文化部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[0] as $id => $record) {
    if ('行政院文化建設委員會 主任委員' == $record['group']) {
        $k = '行政院文化建設委員會/主任委員';
    } elseif ('文化部 部長' == $record['group']) {
        $k = '文化部/部長';
    } else {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}


$k = '科技部/部長';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables[0] as $id => $record) {
    if ('行政院國家科學委員會 主任委員' == $record['group']) {
        $k = '行政院國家科學委員會/主任委員';
    } elseif ('科技部 部長' == $record['group']) {
        $k = '科技部/部長';
    } else {
        continue;
    }

    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '國家發展委員會/主任委員';
$tables = WikiTableParser::getTable('中華民國' . explode('/', $k)[0]);
foreach ($tables as $table) {
    foreach ($table as $id => $record) {
        $k = str_replace(' ', '/', $record['group']);
        $add_result(
            $k,
            $record['姓名'],
            $record['就職時間'],
            $record['卸任時間']
        );
    }
}

$k = '行政院農業委員會/主任委員';
$tables = WikiTableParser::getTable('行政院農業委員會'); //
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院大陸委員會/主任委員';
$tables = WikiTableParser::getTable('行政院大陸委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '蒙藏委員會/委員長';
$tables = WikiTableParser::getTable('蒙藏委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '金融監督管理委員會/主任委員';
$tables = WikiTableParser::getTable('金融監督管理委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '僑務委員會/委員長';
$tables = WikiTableParser::getTable('僑務委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院環境保護署/署長';
$tables = WikiTableParser::getTable('行政院環境保護署');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院海岸巡防署/署長';
$tables = WikiTableParser::getTable('行政院海岸巡防署');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '國軍退除役官兵輔導委員會/主任委員';
$tables = WikiTableParser::getTable('國軍退除役官兵輔導委員會');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '原住民族委員會/主任委員';
$tables = WikiTableParser::getTable('原住民族委員會');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '客家委員會/主任委員';
$tables = WikiTableParser::getTable('客家委員會');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院公共工程委員會/主任委員';
$tables = WikiTableParser::getTable('行政院公共工程委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '中央銀行/總裁';
$tables = WikiTableParser::getTable('中華民國中央銀行');
foreach ($tables[0] as $id => $record) {
    if ('總裁時期' !== $record['group']) {
        continue;
    }
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院主計總處/主計長';
$tables = WikiTableParser::getTable('行政院主計總處');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院人事行政總處/人事長';
$tables = WikiTableParser::getTable('行政院人事行政總處');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '國立故宮博物院/院長';
$tables = WikiTableParser::getTable('國立故宮博物院');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '行政院原子能委員會/主任委員';
$tables = WikiTableParser::getTable('行政院原子能委員會');
foreach ($tables[0] as $id => $record) {
    $k = str_replace(' ', '/', $record['group']);
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '中央選舉委員會/主任委員';
$tables = WikiTableParser::getTable('中華民國中央選舉委員會');
foreach ($tables[1] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就職時間'],
        $record['卸任時間']
    );
}

$k = '公平交易委員會/主任委員';
$tables = WikiTableParser::getTable('中華民國公平交易委員會');
foreach ($tables[0] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['就任時間'],
        $record['卸任時間']
    );
}

$k = '國家通訊傳播委員會/主任委員';
$tables = WikiTableParser::getTable('國家通訊傳播委員會');
foreach ($tables as $table) {
    foreach ($table as $id => $record) {
        if (false === strpos($record['備註'], '主任委員')) {
            continue;
        }
        if (false !== strpos($record['備註'], '副主任委員')) {
            continue;
        }
        preg_match('#(\d+年\d+月\d+日)—(\d+年\d+月\d+日)#', $record['group'], $matches);
    $add_result(
        $k,
        $record['姓名'],
        $matches[1],
        $matches[2]
    );
}
}

$k = '行政院/院長';
$tables = WikiTableParser::getTable('行政院院長');
foreach ($tables[1] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['任期/到任時間'],
        $record['任期/卸任時間']
    );
}

$k = '行政院/副院長';
$tables = WikiTableParser::getTable('行政院副院長');
foreach ($tables[1] as $id => $record) {
    $add_result(
        $k,
        $record['姓名'],
        $record['任期/到任時間'],
        $record['任期/卸任時間']
    );
}

foreach ($result as $record) {
    fputcsv($output, $record);
}
