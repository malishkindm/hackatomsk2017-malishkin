<?php
namespace App\Services;

use App\Enums\GameTypes;
use App\Models\GameOffer;
use \DB;

/**
 * Сервис по учету предложений и поиска игр
 */
class GameOfferService
{
    /**
     * @var GameOffer
     */
    private $model;

    /**
     * @param GameOffer $model
     */
    public function __construct(GameOffer $model)
    {
        $this->model = $model;
    }

    /**
     * Создать новое предложение об игре
     * @param int    $userId
     * @param string $type
     * @param int    $bet
     * @return string
     */
    public function addOffer(int $userId, string $type, int $bet): string
    {
        $this->dropAllOffers($userId);

        $bet = $type == GameTypes::FLOAT_BET ? $bet : ($type == GameTypes::FIXED_BET ? conf('game.fixed_bet') : 0);

        $gameKey = str_random(6);

        $this->model->fill([
            'user_id'  => $userId,
            'type'     => $type,
            'bet'      => $bet,
            'game_key' => $gameKey,
        ])->save();

        return $gameKey;
    }

    /**
     * Поиск предожения игры по заданному типу и ставке
     *
     * Смысл цикла внутри: исключаем ситуацию гонки, когда на одно предложение ответят несколько игроков. Если
     * предложение будет найдено, забираем его из базы (удаляем). Тогда инфу по нему получит только один
     * из откликнувшихся.
     *
     * @param int    $userId id пользователя, желающего сыграть
     * @param string $type   тип игры
     * @param int    $bet    ставка
     * @return GameOffer|null
     */
    public function searchGame(int $userId, string $type, int $bet): ?GameOffer
    {
        while (1) {
            /* @var GameOffer $offer */
            $offer = $this->model
                ->where('user_id', '<>', $userId)
                ->where('type', $type)
                ->where('bet', $bet)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$offer) {
                return null;
            }

            $deleted = $this->dropAllOffers($offer->user_id);

            if ($deleted > 0 || !$offer) {
                return $offer;
            }
        }
    }

    /**
     * Удалить все предложения юзера об играх. Их может быть несколько, сносим все.
     *
     * Реальное удаление записи, не soft-delete
     *
     * @param int $userId id пользователя
     * @return int
     */
    public function dropAllOffers(int $userId): int
    {
        //return $this->model->where('user_id', $userId)->delete();
        // Eloquent не возвращает количество затронутых записей, поэтому такой запрос
        return (int)DB::delete('DELETE FROM game_offers WHERE user_id=:uid', [':uid' => $userId]);
    }
}
