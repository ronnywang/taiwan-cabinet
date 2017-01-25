<?php

$content = file_get_contents('https://zh.wikipedia.org/zh-tw/%E4%B8%AD%E8%8F%AF%E6%B0%91%E5%9C%8B%E6%94%BF%E5%8B%99%E5%A7%94%E5%93%A1%E5%88%97%E8%A1%A8');
$doc = new DOMDocument;
$doc->loadHTML($content);
echo "職稱,姓名,到職,卸任,性別,維基百科條目\n";
foreach ($doc->getElementsByTagName('li') as $li_dom) {
    foreach ($li_dom->getElementsByTagName('a') as $a_dom) {
        $href = $a_dom->getAttribute('href');
        $name = $a_dom->nodeValue;
        if (!$a_dom->nextSibling) {
            continue;
        }
        $text = $a_dom->nextSibling->nodeValue;
        $title = $a_dom->getAttribute('title');

        if (preg_match_all('#(先生|女士)\s*（任期：([0-9.]+)\s*-\s*([0-9.]+)?\s*）#u', $text, $matches_all)) {
            foreach ($matches_all[0] as $id => $t) {
                $matches = array_map(function($m) use ($id, $matches_all) {
                    return $matches_all[$m][$id];
                }, array_keys($matches_all));
                $gender = $matches[1] == '先生' ? 'M' : 'F';
                $name = trim($name);
                list($sy, $sm, $sd) = explode('.', $matches[2]);
                $sy += 1911;
                if (array_key_exists(3, $matches)) {
                    list($ey, $em, $ed) = explode('.', $matches[3]);
                    $ey += 1911;
                    echo "行政院/政務委員,{$name},{$sy}年{$sm}月{$sd}日,{$ey}年{$em}月{$ed}日,{$gender},{$title}\n";
                } else {
                    echo "行政院/政務委員,{$name},{$sy}年{$sm}月{$sd}日,2016年5月20日,{$gender},{$title}\n";
                }
            }
        } else if (preg_match('#（(\d+年\d+月\d+日)－\s*）#', $text, $matches)) {
            echo "行政院/政務委員,{$name},{$matches[1]},,,,{$title}\n";
        }
    }

}
