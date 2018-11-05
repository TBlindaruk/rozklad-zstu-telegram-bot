<?php
declare(strict_types = 1);

namespace Longman\TelegramBot\Commands\SystemCommands;

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Keyboard;
use Longman\TelegramBot\Entities\Message;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use WhereIsMyTeacherBot\Service\TeacherService;
use ZSTU\RozkladClient\Client;
use ZSTU\RozkladClient\V1\Teacher\ResponseData\TeacherCollection;

/**
 * Class GenericmessageCommand
 *
 * @package Longman\TelegramBot\Commands\SystemCommands
 */
class GenericmessageCommand extends SystemCommand
{
    /**
     * @var TeacherService
     */
    private $teacherService;

    /**
     * @var Client
     */
    private $rozkladClient;

    /**
     * @var string
     */
    protected $name = 'Generic';

    /**
     * @var string
     */
    protected $version = '0.0.1';

    /**
     * GenericmessageCommand constructor.
     *
     * @param Telegram    $telegram
     * @param Update|null $update
     */
    public function __construct(Telegram $telegram, Update $update = null)
    {
        parent::__construct($telegram, $update);
        $this->teacherService = new TeacherService();
        $this->rozkladClient = new Client();
    }

    /**
     * Command execute method
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $answer = 'Вибачте, я не розумію вас. Команда /help допоможе вам';

        $searchedTeacher = $this->rozkladClient->v1()->teacher()->search($message->getText());

        if ($searchedTeacher->getSearched()) {
            $answer = $this->teacherService->getSchedule($message->getText());

            return Request::sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => $answer,
                'parse_mode' => 'HTML',
            ]);
        }

        if ($searchedTeacher->getSuggest()->isNotEmpty()) {
            $data = $this->searchTextOccurrences($searchedTeacher->getSuggest(), $message);

            return Request::sendMessage($data);
        }

        $data = [
            'chat_id' => $message->getChat()->getId(),
            'text' => $answer,
            'parse_mode' => 'Markdown',
        ];

        return Request::sendMessage($data);
    }

    /**
     * @param TeacherCollection $teachers
     * @param Message           $message
     *
     * @return array|null
     */
    private function searchTextOccurrences(TeacherCollection $teachers, Message $message): ?array
    {
        $patterns = [];
        foreach ($teachers->all() as $teacher) {
            $patterns[] = [$teacher->getTeacherName()];
        }
        if (!empty($patterns)) {
            $keyboard = new Keyboard(...$patterns);

            $keyboard->setResizeKeyboard(true)
                ->setOneTimeKeyboard(true)
                ->setSelective(false);

            $data = [
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Виберіть викладача якого ви шукаєте',
                'reply_markup' => $keyboard,
            ];

            return $data;
        }

        return null;
    }
}