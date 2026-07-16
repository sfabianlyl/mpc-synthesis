<?php

namespace App\Filament\Resources\UploadedDocumentSource\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UploadedDocumentSourcesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('font-medium')
                    ->limit(40),

                TextColumn::make('original_filename')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30)
                    ->copyable()
                    ->copyMessage('File path copied')
                    ->copyMessageDuration('sm'),

                TextColumn::make('document_type')
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'encyclical' => 'primary',
                        'pastoral_letter' => 'info',
                        'apostolic_exhortation' => 'success',
                        'conference_document' => 'warning',
                        'homily' => 'danger',
                        'general_audience' => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'encyclical' => 'Encyclical',
                        'pastoral_letter' => 'Pastoral Letter',
                        'apostolic_exhortation' => 'Apostolic Exhortation',
                        'conference_document' => 'Conference Doc',
                        'homily' => 'Homily',
                        'general_audience' => 'General Audience',
                        default => ucfirst($state ?? 'unknown'),
                    }),

                TextColumn::make('author')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('chunk_count')
                    ->counts('chunks')
                    ->sortable()
                    ->badge()
                    ->numeric(),

                TextColumn::make('processing_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->sortable(),

                TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TextColumn::filter('title')
                    ->label('Search Documents'),

                \Filament\Tables\Filters\SelectFilter::make('document_type')
                    ->label('Document Type')
                    ->options([
                        'encyclical' => 'Encyclical',
                        'pastoral_letter' => 'Pastoral Letter',
                        'apostolic_exhortation' => 'Apostolic Exhortation',
                        'conference_document' => 'Conference Document',
                        'homily' => 'Homily',
                        'general_audience' => 'General Audience',
                        'other' => 'Other',
                    ])
                    ->native(false),

                \Filament\Tables\Filters\SelectFilter::make('processing_status')
                    ->label('Processing Status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                \Filament\Tables\Actions\ViewAction::make(),
                EditAction::make(),
                \Filament\Tables\Actions\Action::make('process_document')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation('This will extract text, convert to markdown, chunk the document, and generate embeddings. Continue?')
                    ->action(function ($record) {
                        $chunkingService = app(\App\Services\DocumentChunkingService::class);
                        $chunkingService->chunkDocument($record);

                        return redirect()->back();
                    })
                    ->visible(fn ($record) => $record->processing_status === 'completed' || $record->processing_status === 'failed'),
                \Filament\Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($record->file_path);
                        app(\App\Services\DocumentChunkingService::class)->deleteChunks($record->id);
                    }),
            ])
            ->bulkActions([
                BulkAction::make('process_selected')
                    ->icon('heroicon-o-play')
                    ->requiresConfirmation(function ($records) {
                        return 'Process '.count($records).' selected document(s)? This will extract text, convert to markdown, chunk each document, and generate embeddings.';
                    })
                    ->action(function ($records) {
                        $chunkingService = app(\App\Services\DocumentChunkingService::class);
                        foreach ($records as $record) {
                            $chunkingService->chunkDocument($record);
                        }
                    })
                    ->deselectAfterCompletion(),
                DeleteBulkAction::make()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($record->file_path);
                            app(\App\Services\DocumentChunkingService::class)->deleteChunks($record->id);
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
