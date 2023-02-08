<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TextGenerator extends Model
{
    use HasFactory;

    // Метод замены синонимов
    // Чтобы получить готовый текст - статический метод TextGenerator::ReadyText($param1..)
    public static function ReadyText($id, $page_article, $text, $replacers)
    {
        $id = $page_article . '_' . $id; // Строим id вида Article_{$id}
        $text = self::setVariables($text, $replacers); // Заменяем все переменные вида {$var}
        $matches = self::cutSinonims($text); // Заменяем синонимы  {синоним1|синоним2|синоним3|синоним2}
        $databaseValues = self::getDatabase($id); // Забираем значения из базы
        $has_inDatabase = self::checkDifferent($matches, $databaseValues); // Проверяем есть ли в базе

        return self::returnText($id, $has_inDatabase, $text, $matches, $databaseValues); // Возвращаем готовый текст
    }

    // Вырезаем синонимы в отдельный массив
    private function cutSinonims($text)
    {
        preg_match_all(
            "/\{(.*|.*)+\}/U",
            $text,
            $matches
        );
        return $matches[0];
    }

    // Заменяем переменные в тексте '.. {$var} ..' = '.. value ..' 
    private function setVariables($text, $vars)
    {
        if (!isset($vars)) {
            return $text;
        }

        return str_replace(
            array_keys($vars),
            array_values($vars),
            $text
        );
    }

    // Забираем последнюю подходящую запись из базы
    private function getDatabase($id)
    {
        $data = self::where('target_id', $id)->get()->last();
        if (isset($data)) {
            if (isset($data['value'])) {
                return json_decode($data['value'], true);
            } else {
                return false;
            }
        }
    }

    // Сравниваем есть ли такое в базе
    private function checkDifferent($local, $db)
    {
        if (!is_array($db)) {
            return false;
        }

        $temp_local = [];
        $temp_db = [];

        foreach ($db as $item) {
            // Убивем {$var}
            if (strpos($item['template'], '|')) {
                $temp_db[] = $item['template'];
            }
        }
        foreach ($local as $item) {
            // Убивем {$var}
            if (strpos($item, '|')) {
                $temp_local[] = $item;
            }
        }
        return $temp_local == $temp_db;
    }

    // Возвращаем готовый текст
    private function returnText($id, $has_inDatabase = false, $text, $matches, $db)
    {
        if ($has_inDatabase) {

            // Приводим масив к единому виду с matches
            foreach ($db as $key => $item) {
                $temp[] = $item['value'];
            }
            // Заменяем {..|..|..} на то, что в базе
            return str_replace(
                array_values($matches),
                array_values($temp),
                $text
            );
        } else {
            // Пробегаемся по {..|..|..} 
            foreach ($matches as $item) {
                $values = preg_replace("/(^{|}$)/m", '', $item);
                $values = explode('|', $values);
                // Выбираем случайный
                $value = $values[array_rand($values)];
                // Приводим к номральному виду (для записи в БД)
                $sinonim_replacers[] = [
                    'template' => $item,
                    'value' => $value
                ];
                // Заполняем все готовые элементы в один массив
                $result[] = $value;
            }
            // Записываем в базу
            if (self::where('target_id', $id)->get()->last()) {
                self::where('target_id', $id)
                    ->get()
                    ->last()
                    ->update(array('value' => json_encode($sinonim_replacers, JSON_UNESCAPED_UNICODE)));
            } else {
                self::create(['target_id' => $id, 'value' => json_encode($sinonim_replacers, JSON_UNESCAPED_UNICODE)]);
            }

            return str_replace(
                array_values($matches),
                array_values($result),
                $text
            );
        }
    }
}
