<?php

namespace App\Filament\Pages;

use App\Settings\SiteSettings;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;

class ManageSettings extends SettingsPage
{
    protected static ?string $navigationLabel = 'Configurações do site';

    protected static string|\UnitEnum|null $navigationGroup = 'Configurações';

    protected static string $settings = SiteSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('site_name')
                ->label('Nome do site')
                ->maxLength(255),
        ]);
    }
}
