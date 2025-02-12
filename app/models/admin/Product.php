<?php

namespace app\models\admin;

use app\models\AppModel;
use RedBeanPHP\R;

class Product extends AppModel
{
    public array $attributes = [
        'title' => '',
        'alias' => '',
        'content' => '',
        'price' => '',
        'old_price' => 0,
        'keywords' => '',
        'description' => '',
        'status' => '',
        'hit' => '',
        'img' => '',
    ];

    public array $rules = [
        'required' => [
            ['title'],
            ['price'],
        ],
    ];


    public function editRelatedProduct($id, $data)
    {
        $related_product = R::getCol("SELECT related_id FROM related_product WHERE product_id = ?", [$id]);
        // если менеджер убрал связанные товары
        if(empty($data['related']) && !empty($related_product)){
            R::exec("DELETE FROM related_product WHERE product_id = ?", [$id]);
            return;
        }

        // если связанные товары добавляются
        if(empty($related_product) && !empty($data['related'])){
            $sql_part = '';
            foreach($data['related'] as $v){
                $v = (int)$v;
                $sql_part .= "($id, $v),";
            }
            $sql_part = rtrim($sql_part, ',');
            R::exec("INSERT INTO related_product (product_id, related_id) VALUES $sql_part");
            return;
        }

        // если изменились связанные товары - удалим и запишем новые
        if(!empty($data['related'])){
            $result = array_diff($related_product, $data['related']);

            if(!empty($result) || count($related_product) != count($data['related'])){
                R::exec("DELETE FROM related_product WHERE product_id = ?", [$id]);
                $sql_part = '';
                foreach($data['related'] as $v){
                    $sql_part .= "($id, $v),";
                }
                $sql_part = rtrim($sql_part, ',');
                debug($sql_part);
                R::exec("INSERT INTO related_product (product_id, related_id) VALUES $sql_part");
            }
        }
    }

    public function editFilter($id, $data)
    {
        $attrs = [];
        array_walk_recursive($data['attrs'], function ($item, $key) use (&$attrs) {
            $attrs[] = $item;
        });
        $data['attrs'] = $attrs;

        $filter = R::getCol("SELECT attr_id FROM attribute_product WHERE product_id = ?", [$id]);
        // если менеджер убрал фильтры
        if(empty($data['attrs']) && !empty($filter)){
            R::exec("DELETE FROM attribute_product WHERE product_id = ?", [$id]);
            return;
        }
        // если фильтры добавляются
        if(empty($filter) && !empty($data['attrs'])){
            $sql_part = '';
            foreach($data['attrs'] as $v){
                $sql_part .= "($v, $id),";
            }
            $sql_part = rtrim($sql_part, ',');
            R::exec("INSERT INTO attribute_product (attr_id, product_id) VALUES $sql_part");
            return;
        }

        // если изменились фильтры - удалим и запишем новые
        if(!empty($data['attrs'])){
            $result = array_diff($filter, $data['attrs']);
            
            if(!$result || count($filter) != count($data['attrs'])){
                R::exec("DELETE FROM attribute_product WHERE product_id = ?", [$id]);
                $sql_part = '';
                foreach($data['attrs'] as $v){
                    $sql_part .= "($v, $id),";
                }

                $sql_part = rtrim($sql_part, ',');

                R::exec("INSERT INTO attribute_product (attr_id, product_id) VALUES $sql_part");
            }else{
                foreach($result as $item){
                    $data['attrs'][] = $item;
                }
                R::exec("DELETE FROM attribute_product WHERE product_id = ?", [$id]);
                $sql_part = '';
                foreach($data['attrs'] as $v){
                    $sql_part .= "($v, $id),";
                }

                $sql_part = rtrim($sql_part, ',');

                R::exec("INSERT INTO attribute_product (attr_id, product_id) VALUES $sql_part");
            }
        }
    }

    public function editCategory($id, $data)
    {
        if(isset($data['cats']) && !empty($data['cats'])){
            R::exec("DELETE FROM category_product WHERE product_id = ?", [$id]);
            $sql_part = '';
            foreach($data['cats'] as $v){
                $sql_part .= "($v, $id),";
            }

            $sql_part = rtrim($sql_part, ',');

            R::exec("INSERT INTO category_product (category_id, product_id) VALUES $sql_part");
        }else{
            $_SESSION['error'] = "Необходимо указать категорию";
            redirect();
        }
    }

    public static function getCats($product_id)
    {
        $catIds = R::getCol("SELECT category_id FROM category_product WHERE product_id = ?", [$product_id]);
        $catIds = implode(',', $catIds);
        $cats = R::getAll("SELECT alias, title FROM category WHERE id IN ($catIds)");
        $links = '';
        $path = PATH;
        foreach($cats as $item){
            $links .= "<a href='{$path}/category/{$item['alias']}'>{$item['title']}</a>" . ', ';
        }
        return trim($links, ', ');
    }

    public function getImg(){
        if(!empty($_SESSION['single'])){
            $this->attributes['img'] = $_SESSION['single'];
            unset($_SESSION['single']);
        }
    }

    public function saveGallery($id){
        if(!empty($_SESSION['multi'])){
            $sql_part = '';
            foreach($_SESSION['multi'] as $v){
                $sql_part .= "('$v', $id),";
            }
            $sql_part = rtrim($sql_part, ',');
            R::exec("INSERT INTO gallery (img, product_id) VALUES $sql_part");
            unset($_SESSION['multi']);
        }
    }

    public function uploadImg($name, $wmax, $hmax){
        $uploaddir = WWW . '/img/photos/';

        $ext = strtolower(preg_replace("#.+\.([a-z]+)$#i", "$1", $_FILES['file']['name'])); // расширение картинки
        $types = array("image/gif", "image/png", "image/jpeg", "image/pjpeg", "image/x-png", "image/svg+xml", "image/webp"); // массив допустимых расширений
        if($_FILES['file']['size'] > 1048576){
            $res = array("error" => "Ошибка! Максимальный вес файла - 1 Мб!");
            exit(json_encode($res));
        }
        if($_FILES['file']['error']){
            $res = array("error" => "Ошибка! Возможно, файл слишком большой.");
            exit(json_encode($res));
        }
        if(!in_array($_FILES['file']['type'], $types)){
            $res = array("error" => "Допустимые расширения - .gif, .jpg, .png, .svg, .webp");
            exit(json_encode($res));
        }
        $i = rand(1,19);
        $new_name = md5(time().$i).".$ext";
        $uploadfile = $uploaddir.$new_name;

        if(@move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)){
            if($name == 'single'){
                $_SESSION['single'] = 'img/photos/'.$new_name;
            }else{
                $_SESSION['multi'][] = 'img/photos/'.$new_name;
            }

            self::resize($uploadfile, $uploadfile, $wmax, $hmax, $ext);
            $res = array("file" => $new_name);
            exit(json_encode($res));
        }
    }

    /**
     * @param string $target путь к оригинальному файлу
     * @param string $dest путь сохранения обработанного файла
     * @param string $wmax максимальная ширина
     * @param string $hmax максимальная высота
     * @param string $ext расширение файла
     */
    public static function resize($target, $dest, $wmax, $hmax, $ext){
        list($w_orig, $h_orig) = getimagesize($target);
        $ratio = $w_orig / $h_orig; // =1 - квадрат, <1 - альбомная, >1 - книжная

        if(($wmax / $hmax) > $ratio){
            $wmax = $hmax * $ratio;
        }else{
            $hmax = $wmax / $ratio;
        }

        $img = "";
        // imagecreatefromjpeg | imagecreatefromgif | imagecreatefrompng
        switch($ext){
            case("gif"):
                $img = imagecreatefromgif($target);
                break;
            case("png"):
                $img = imagecreatefrompng($target);
                break;
            case("webp"):
                $img = imagecreatefromwebp($target);
                break;
            default:
                $img = imagecreatefromjpeg($target);
        }
        // $webp = imagewebp($img, str_replace(['jpg', 'png', 'jpeg', ], ['webp', 'webp', 'webp'], $target));
        $newImg = imagecreatetruecolor((int)$wmax, (int)$hmax); // создаем оболочку для новой картинки

        if($ext == "png"){
            imagesavealpha($newImg, true); // сохранение альфа канала
            $transPng = imagecolorallocatealpha($newImg,0,0,0,127); // добавляем прозрачность
            imagefill($newImg, 0, 0, $transPng); // заливка
        }
        if($ext == "webp"){
            imagesavealpha($newImg, true); // сохранение альфа канала
            $transWebP = imagecolorallocatealpha($newImg,0,0,0,127); // добавляем прозрачность
            imagefill($newImg, 0, 0, $transWebP); // заливка
        }

        imagecopyresampled($newImg, $img, 0, 0, 0, 0, (int)$wmax, (int)$hmax, $w_orig, $h_orig); // копируем и ресайзим изображение
        switch($ext){
            case("gif"):
                imagegif($newImg, $dest);
                break;
            case("png"):
                imagepng($newImg, $dest);
                break;
            case("webp"):
                imagewebp($newImg, $dest);
                break;
            default:
                imagejpeg($newImg, $dest);
                imagewebp($newImg, str_replace(['jpg', 'jpeg', ], ['webp', 'webp'], $dest));
        }
        imagedestroy($newImg);
    }
}