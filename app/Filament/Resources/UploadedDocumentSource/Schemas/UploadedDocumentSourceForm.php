<?php

namespace App\Filament\Resources\UploadedDocumentSource\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UploadedDocumentSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('pdf_file')
                    ->label('PDF File')
                    ->required()
                    ->disk('public')
                    ->directory('document-sources/pdfs')
                    ->maxSize(52428800) // 50MB
                    ->acceptedFileTypes(['application/pdf'])
                    ->storeFiles(true)
                    ->live()
                    ->afterStateUpdated(fn (callable $set, ?string $state) => $set('original_filename', $state ? basename($state) : null))
                    ->hiddenOn('edit')
                    ->columnSpanFull(),

                TextInput::make('title')
                    ->required()
                    ->maxLength(500)
                    ->placeholder('e.g., Evangelii Gaudium')
                    ->debounce(500)
                    ->columnSpanFull(),

                Select::make('document_type')
                    ->required()
                    ->options([
                        'encyclical' => 'Encyclical',
                        'pastoral_letter' => 'Pastoral Letter',
                        'apostolic_exhortation' => 'Apostolic Exhortation',
                        'conference_document' => 'Conference Document',
                        'homily' => 'Homily',
                        'general_audience' => 'General Audience',
                        'other' => 'Other',
                    ])
                    ->native(false)
                    ->searchable(),

                TextInput::make('author')
                    ->label('Author')
                    ->placeholder('e.g., Pope Francis')
                    ->columnSpanFull(),

                DatePicker::make('publication_date')
                    ->label('Publication Date')
                    ->native(false),

                Textarea::make('cleaned_markdown')
                    ->columnSpanFull()
                    ->rows(15)
                    ->disabled()
                    ->visible(fn (callable $get) => $get('processing_status') === 'completed')
                    ->label('Extracted Markdown Content'),

                TextInput::make('original_filename')
                    ->disabled()
                    ->dehydrated()
                    ->visible(fn (?Model $record) => $record !== null)
                    ->label('Original Filename'),

                Select::make('processing_status')
                    ->disabled()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->visible(fn (?Model $record) => $record !== null)
                    ->label('Processing Status'),

                TextInput::make('chunk_count')
                    ->disabled()
                    ->numeric()
                    ->visible(fn (?Model $record) => $record !== null && $record->chunk_count > 0)
                    ->label('Chunk Count'),
            ]);
    }
}
