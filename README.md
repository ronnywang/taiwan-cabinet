# taiwan-cabinet
透過 wikipedia 的資料，找出中華民國歷年來的閣員名單、性別、生日等，以便計算歷年的性別比例和平均年齡變化比較

使用方式
========
```
php get_zhengwei_list.php > 行政院-政務委員.csv # 產生政務委員名單
php get_table_from_wiki.php > 行政院-閣員.csv # 產生政委以外閣員名單
php get_info_from_wiki.php > result.csv # 把上面兩個檔案再從 wikipedia 找出成員的性別和生日
```
