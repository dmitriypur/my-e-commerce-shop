<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Произошла ошибка</title>
</head>
<body>
<h1>Произошла ошибка</h1>
<p><b>Код ошибки: </b><?=$errno;?> - <?=$response;?></p>
<p><b>Текст ошибки: </b><?=$errstr;?></p>
<p><b>Файл в котором произошла ошибка: </b><?=$errfile;?></p>
<p><b>Строка в которой произошла ошибка: </b><?=$errline;?></p>
</body>
</html>