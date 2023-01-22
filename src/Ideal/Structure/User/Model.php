<?php
/**
 * Ideal CMS (http://idealcms.ru/)
 *
 * @link      http://github.com/ideals/idealcms репозиторий исходного кода
 * @copyright Copyright (c) 2012-2018 Ideal CMS (http://idealcms.ru/)
 * @license   http://idealcms.ru/license.html LGPL v3
 */

namespace Ideal\Structure\User;

use Ideal\Core\Config;
use Ideal\Core\Db;

/**
 * Класс для работы с пользователем
 */
class Model
{

    /** @var  mixed Хранит в себе копию соответствующего объекта поля (паттерн singleton) */
    protected static $instance;

    /** @var array Массив с данными пользователя */
    public $data = [];

    /** @var string Последнее сообщение об ошибке */
    public string $errorMessage = '';

    /** @var string Наименование сессии и cookies */
    protected string $seance;

    /** @var array Считанная сессия этого сеанса */
    protected $session = [];

    /** @var string Название таблицы, в которой хранятся данные пользователей */
    protected string $table = 'ideal_structure_user';

    /** @var string Поле, используемое в качестве логина */
    protected string $loginRow = 'email';

    /** @var string Название поля логина (используется для выдачи уведомлений) */
    protected string $loginRowName = 'e-mail';

    /**
     * Считывает данные о пользователе из сессии
     * @param string|null $seance Кастомизированная переменная сеанса
     */
    public function __construct(?string $seance = null)
    {
        // Запуск сессий только в случае, если они не запущены
        if (session_id() === '') {
            // Для корректной работы этого класса нужны сессии
            session_start();
        }

        $config = Config::getInstance();

        // Устанавливаем имя связанной таблицы
        $this->table = $config->db['prefix'] . $this->table;

        // Инициализируем переменную сеанса
        $this->seance = $seance ?? $config->domain;

        // Загружаем данные о пользователе, если запущена сессия
        if (isset($_SESSION[$this->seance])) {
            $this->session = unserialize($_SESSION[$this->seance], ['allowed_classes' => false]);
            $this->data = $this->session['user_data'];
        } else {
            $this->data = [];
        }
    }

    /**
     * Обеспечение паттерна singleton
     *
     * Особенность — во всех потомках нужно обязательно определять свойство
     * protected static $instance
     *
     * @param string|null $seance Кастомизированная переменная сеанса
     * @return mixed
     */
    public static function getInstance(?string $seance = null)
    {
        if (empty(static::$instance)) {
            $className = static::class;
            static::$instance = new $className($seance);
        }
        return static::$instance;
    }

    /**
     * Проверка залогинен ли пользователь
     *
     * @return bool Если залогинен — true, иначе — false
     */
    public function checkLogin(): bool
    {
        // Если пользователь не залогинен - возвращаем false
        return isset($this->data['ID']);
    }

    /**
     * Проверка введённого пароля
     *
     * В случае удачной авторизации заполняется поле $this->data
     *
     * @param string $login Имя пользователя
     * @param string $pass  Пароль в md5()
     *
     * @return bool Если удалось авторизоваться — true, если не удалось — false
     * @noinspection MultipleReturnStatementsInspection
     */
    public function login(string $login, string $pass): bool
    {
        $login = trim($login);
        $pass = trim($pass);

        // Если не указан логин или пароль - выходим с false
        if (!$login || !$pass) {
            $this->errorMessage = "Необходимо указать и $this->loginRowName, и пароль.";
            return false;
        }

        $user = $this->getUser($login, $pass);

        // Если пользователь находится в процессе активации аккаунта
        if (!isset($user['act_key']) || $user['act_key'] !== '') {
            $this->errorMessage = 'Этот аккаунт не активирован.';
        }

        if ($this->errorMessage !== '') {
            return false;
        }

        // Обнуляем счётчик неудачных попыток авторизации
        $user['counter_failures'] = 0;

        $user['last_visit'] = time();
        $this->data = $user;

        // Обновляем запись о последнем визите пользователя
        $userParams = [
            'last_visit' => $user['last_visit'],
            'counter_failures' => $user['counter_failures'],
        ];

        $db = Db::getInstance();
        $db->update($this->table)->set($userParams);
        $db->where('ID=:id', ['id' => $user['ID']])->exec();

        // Записываем данные о пользователе в сессию
        $this->session['user_data'] = $this->data;

        $_SESSION[$this->seance] = serialize($this->session);

        return true;
    }

    /**
     * Выход пользователя с удалением данных из сессии
     */
    public function logout(): void
    {
        $this->session = [];
        $this->data = [];
        unset($_SESSION[$this->seance]);
    }

    /**
     * Установка произвольного поля для логина пользователя
     *
     * @param string $loginRow Название поля (например, email)
     * @param string $loginRowName Название поля для отображения уведомлений (например, e-mail)
     */
    public function setLoginField(string $loginRow, string $loginRowName): void
    {
        $this->loginRow = $loginRow;
        $this->loginRowName = $loginRowName;
    }

    /**
     * Получение пользователя из БД
     *
     * @param string $login
     * @param string $pass
     *
     * @return array
     */
    private function getUser(string $login, string $pass): array
    {
        // Получаем пользователя с указанным логином
        $db = Db::getInstance();
        $_sql = "SELECT * FROM $this->table WHERE is_active = 1 AND $this->loginRow = :login";
        $user = $db->select($_sql, ['login' => $login]);
        if (count($user) === 0) {
            $this->errorMessage = "Неверно указаны $this->loginRowName или пароль.";
            return [];
        }
        $user = $user[0];

        // Если пользователь с таким логином не нашлось, или пароль не совпал - выходим с false
        if (($user[$this->loginRow] === '')
            || (crypt($pass, $user['password']) !== $user['password'])
        ) {
            // Увеличиваем значение счётчика неудачных попыток авторизации если он меньше 12
            if ($user['counter_failures'] < 12) {
                $db->update($this->table)->set(['counter_failures' => $user['counter_failures'] + 1]);
                $db->where($this->loginRow . ' = :login', ['login' => $login])->exec();
            }

            $this->logout();
            $this->errorMessage = "Неверно указаны $this->loginRowName или пароль.";

            // Придерживаем ответ на значение равное умножению счётчика неудачных попыток авторизации на 5
            sleep($user['counter_failures'] * 5);
            $user = [];
        }

        return $user;
    }
}
