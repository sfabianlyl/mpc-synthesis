import { Head, usePage } from '@inertiajs/react';
import { Cloud, File, FileAudio, FileVideo, Image, Trash2, Upload, X } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { Conversation } from '@/types/models';

interface PageProps {
    conversations: Conversation[];
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function getFileIcon(mimeType: string | null): React.ReactNode {
    if (!mimeType) return <File className="size-4 text-muted-foreground" />;

    if (mimeType.startsWith('image/')) return <Image className="size-4 text-purple-500" />;
    if (mimeType.startsWith('audio/')) return <FileAudio className="size-4 text-blue-500" />;
    if (mimeType.startsWith('video/')) return <FileVideo className="size-4 text-red-500" />;
    if (mimeType === 'application/pdf') return <File className="size-4 text-red-400" />;

    return <File className="size-4 text-muted-foreground" />;
}

interface SelectedFile {
    file: File;
    id: string;
}

export default function MediaUploadPage({ conversations }: PageProps) {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const [selectedConversation, setSelectedConversation] = useState<string>('');
    const [files, setFiles] = useState<SelectedFile[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    }, []);

    const addFiles = useCallback((fileList: FileList | File[]) => {
        const newFiles: SelectedFile[] = [];
        for (const file of fileList) {
            const id = `${file.name}-${Date.now()}-${Math.random().toString(36).slice(2)}`;
            newFiles.push({ file, id });
        }
        setFiles((prev) => [...prev, ...newFiles]);
    }, []);

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(false);
            if (e.dataTransfer.files) {
                addFiles(e.dataTransfer.files);
            }
        },
        [addFiles],
    );

    const handleFileInput = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            if (e.target.files) {
                addFiles(e.target.files);
            }
        },
        [addFiles],
    );

    const removeFile = useCallback((id: string) => {
        setFiles((prev) => prev.filter((f) => f.id !== id));
    }, []);

    const clearAllFiles = useCallback(() => {
        setFiles([]);
    }, []);

    const handleUpload = useCallback(async () => {
        if (!selectedConversation || files.length === 0) return;

        setIsUploading(true);
        const formData = new FormData();
        formData.append('conversation_id', selectedConversation);
        for (const { file } of files) {
            formData.append('files[]', file);
        }

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        try {
            const response = await fetch(`/admin/media-upload/${selectedConversation}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            if (response.ok) {
                const result = (await response.json()) as { message?: string };
                toast.success(result.message ?? 'Files uploaded successfully!');
                clearAllFiles();
                setSelectedConversation('');
            } else {
                const error = (await response.json()) as { message?: string };
                toast.error(error.message ?? 'Upload failed. Please try again.');
            }
        } catch {
            toast.error('Network error. Please check your connection and try again.');
        } finally {
            setIsUploading(false);
        }
    }, [selectedConversation, files, clearAllFiles]);

    const canSubmit = selectedConversation !== '' && files.length > 0 && !isUploading;

    // Breadcrumbs are handled by the layout function below

    return (
        <>
            <Head title="Media Upload" />
            <div className="p-8 flex flex-col gap-6 max-w-3xl mx-auto">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-3xl font-bold tracking-tight">Media File Upload</h1>
                    <p className="text-muted-foreground">
                        Upload media files for a specific conversation. Files will be stored in the conversation's media
                        folder.
                    </p>
                </div>

                {/* Conversation Selection */}
                <Card>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg">Conversation</CardTitle>
                        <CardDescription>Select the conversation to upload media files for.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Select value={selectedConversation} onValueChange={setSelectedConversation}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select a conversation..." />
                            </SelectTrigger>
                            <SelectContent>
                                {conversations.length === 0 ? (
                                    <SelectItem value="empty" disabled>
                                        No conversations available
                                    </SelectItem>
                                ) : (
                                    conversations.map((conversation) => (
                                        <SelectItem key={conversation.id} value={String(conversation.id)}>
                                            <div className="flex items-center gap-2 w-full">
                                                <span className="font-medium">{conversation.title}</span>
                                                <span className="text-xs text-muted-foreground ml-auto">
                                                    ({conversation.status})
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                {/* Drag and Drop Upload Area */}
                <Card className={`border-2 transition-colors ${isDragging ? 'border-primary bg-primary/5' : ''}`}>
                    <CardHeader className="pb-4">
                        <CardTitle className="text-lg">Upload Files</CardTitle>
                        <CardDescription>Drag and drop files or click to browse.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            onDragOver={handleDragOver}
                            onDragLeave={handleDragLeave}
                            onDrop={handleDrop}
                            onClick={() => fileInputRef.current?.click()}
                            className="border-2 border-dashed rounded-lg p-12 flex flex-col items-center justify-center gap-4 cursor-pointer transition-colors hover:border-primary hover:bg-accent/30"
                            role="button"
                            tabIndex={0}
                            aria-label="Drop files here or click to browse"
                        >
                            <div className="p-4 bg-muted rounded-full">
                                <Cloud className="size-8 text-muted-foreground" />
                            </div>
                            <div className="text-center">
                                <p className="text-sm font-medium">Drag & drop files here or click to browse</p>
                                <p className="text-xs text-muted-foreground mt-1">
                                    PDF, Images, Audio, Video (max 50MB per file)
                                </p>
                            </div>
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.svg,.mp3,.wav,.ogg,.mp4,.webm,.mov"
                                onChange={handleFileInput}
                                className="hidden"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* File List Preview */}
                {files.length > 0 && (
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-lg">
                                    Files ({files.length})
                                </CardTitle>
                                <Button variant="ghost" size="sm" onClick={clearAllFiles} className="h-8 text-xs">
                                    <X className="size-3 mr-1" />
                                    Clear all
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-2 max-h-60 overflow-y-auto">
                                {files.map(({ file, id }) => (
                                    <div
                                        key={id}
                                        className="flex items-center gap-3 p-3 bg-muted/50 rounded-lg border"
                                    >
                                        {getFileIcon(file.type)}
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium truncate">{file.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatFileSize(file.size)}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                removeFile(id);
                                            }}
                                            className="h-8 w-8 p-0 text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Upload Button */}
                <div className="flex justify-end">
                    <Button
                        onClick={handleUpload}
                        disabled={!canSubmit}
                        size="lg"
                        className="gap-2"
                    >
                        {isUploading ? (
                            <>
                                <span className="animate-spin">⟳</span>
                                Uploading...
                            </>
                        ) : (
                            <>
                                <Upload className="size-4" />
                                Upload Files
                            </>
                        )}
                    </Button>
                </div>

                {/* Info for logged in user */}
                {user && (
                    <p className="text-xs text-muted-foreground text-center">
                        Uploaded by: {user.name} ({user.email})
                    </p>
                )}
            </div>
        </>
    );
}

MediaUploadPage.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Admin', href: '/admin' }, { title: 'Media Upload', href: '/admin/media-upload' }]}>
        {page}
    </AppLayout>
);
