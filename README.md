# taiwan-cabinet
透過 wikipedia 的資料，找出中華民國歷年來的閣員名單、性別、生日等，以便計算歷年的性別比例和平均年齡變化比較

展示頁面： https://ronnywang.github.io/taiwan-cabinet/

使用方式
========
```
php get_zhengwei_list.php > 行政院-政務委員.csv # 產生政務委員名單
php get_table_from_wiki.php > 行政院-閣員.csv # 產生政委以外閣員名單
php get_info_from_wiki.php > result.csv # 把上面兩個檔案再從 wikipedia 找出成員的性別和生日
```

程式授權
========
PHP 爬蟲及 javascript 展示頁面以 BSD License 開放授權

資料授權
========
資料是來自維基百科，以 [創用CC 姓名標示-相同方式分享 3.0 協議](https://zh.wikipedia.org/zh-tw/Wikipedia%3ACC\_BY-SA\_3.0%E5%8D%8F%E8%AE%AE%E6%96%87%E6%9C%AC) 授權
