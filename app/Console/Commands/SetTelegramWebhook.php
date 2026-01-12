<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook {url?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Telegram webhook URL';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $url = $this->argument('url') ?? config('telegram.webhook_url');

        if (!$url) {
            $this->error('Webhook URL is not specified. Please provide it as an argument or set TELEGRAM_WEBHOOK_URL in .env');
            return Command::FAILURE;
        }

        $this->info("Setting webhook to: {$url}");

        if ($telegram->setWebhook($url)) {
            $this->info('Webhook set successfully!');
            return Command::SUCCESS;
        } else {
            $this->error('Failed to set webhook. Check your bot token and URL.');
            return Command::FAILURE;
        }
    }
}
