/**
 * Render text content as React nodes, handling bullet points and plain text.
 */
export const renderContentText = (text: string | null): React.ReactNode => {
    if (!text || !text.trim()) {
        return <p className="text-sm text-muted-foreground italic">No content</p>;
    }

    const lines = text.split('\n').filter((line) => line.trim());

    if (lines.length > 1) {
        return (
            <ul className="space-y-1">
                {lines.map((line, i) => (
                    <li key={i} className="flex items-start gap-2">
                        <span className="text-primary mt-0.5 flex-shrink-0">•</span>
                        <span className="text-sm">{line.replace(/^-+\s*/, '')}</span>
                    </li>
                ))}
            </ul>
        );
    }

    return <p className="text-sm whitespace-pre-wrap">{text}</p>;
};

/**
 * Get initials from a full name string.
 */
export const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
};

/**
 * Map a category key to the corresponding database field name.
 */
export const categoryToFieldName = (categoryKey: string): string => {
    if (categoryKey === 'consideration') return 'consideration_text';
    if (categoryKey === 'others') return 'other_text';
    return `${categoryKey}_text`;
};

/**
 * Get the display name for a category key.
 */
export const getCategoryLabel = (categoryKey: string): string => {
    const labels: Record<string, string> = {
        convergence: 'Convergence',
        consideration: 'Matters for Consideration',
        proposals: 'Proposals',
        others: 'Others',
    };
    return labels[categoryKey] || categoryKey;
};
