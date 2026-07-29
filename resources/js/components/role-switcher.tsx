import { usePage, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { NavItem } from '@/types';

/**
 * RoleSwitcher Component for discernment users.
 *
 * Allows discernment users to toggle between "Pastoral" and "Synthesis" modes.
 * Discernment users have FULL access to both pastoral tools AND synthesis member capabilities.
 * They can also view all DecisionSummary, DecisionDraft, and MasterDecision records.
 * Only renders for users where user.role === 'discernment'.
 *
 * Usage:
 *   Include this component in the app sidebar header or footer.
 *   Use getModeNavigation() to conditionally render navigation items based on current mode.
 */

// Mode storage key
const MODE_STORAGE_KEY = 'discernment-mode';

export type DiscernmentMode = 'pastoral' | 'synthesis';

interface RoleSwitcherProps {
    /** Called when the mode changes. Receives the new mode. */
    onModeChange?: (mode: DiscernmentMode) => void;
}

/**
 * Get the current discernment mode from localStorage.
 * Defaults to 'pastoral' if no mode is stored.
 */
function getCurrentMode(): DiscernmentMode {
    if (typeof window !== 'undefined') {
        const stored = localStorage.getItem(MODE_STORAGE_KEY);
        if (stored === 'pastoral' || stored === 'synthesis') {
            return stored;
        }
    }
    return 'pastoral';
}

/**
 * Save the discernment mode to localStorage.
 */
function saveMode(mode: DiscernmentMode): void {
    if (typeof window !== 'undefined') {
        localStorage.setItem(MODE_STORAGE_KEY, mode);
    }
}

/**
 * Navigation items specific to pastoral mode for discernment users.
 */
export function getPastoralNavigation(): NavItem[] {
    return [
        {
            title: 'Pastoral Dashboard',
            href: '/pastoral/dashboard',
            icon: null as any,
        },
        {
            title: 'Pastoral Reports',
            href: '/pastoral/dashboard',
            icon: null as any,
        },
        {
            title: 'Decisions',
            href: '/pastoral/dashboard',
            icon: null as any,
        },
    ];
}

/**
 * Navigation items specific to synthesis mode for discernment users.
 */
export function getSynthesisNavigation(): NavItem[] {
    return [
        {
            title: 'Synthesis Dashboard',
            href: '/synthesis',
            icon: null as any,
        },
    ];
}

/**
 * Get navigation items based on the current discernment mode.
 * @param mode - The current mode ('pastoral' or 'synthesis')
 * @param baseItems - Base navigation items to prepend (e.g., Dashboard)
 */
export function getModeNavigation(
    mode: DiscernmentMode,
    baseItems: NavItem[] = []
): NavItem[] {
    const modeItems = mode === 'pastoral'
        ? getPastoralNavigation()
        : getSynthesisNavigation();

    return [...baseItems, ...modeItems];
}

export function RoleSwitcher({ onModeChange }: RoleSwitcherProps) {
    const { auth } = usePage().props as any;
    const [mode, setMode] = useState<DiscernmentMode>(getCurrentMode);

    // Check if user is discernment
    const isDiscernment = auth?.user?.role === 'discernment';

    useEffect(() => {
        setMode(getCurrentMode());
    }, []);

    const handleModeChange = (newMode: DiscernmentMode) => {
        saveMode(newMode);
        setMode(newMode);
        onModeChange?.(newMode);

        // Navigate to the appropriate section based on new mode
        if (newMode === 'pastoral') {
            router.visit('/pastoral/dashboard', { preserveState: true });
        } else {
            router.visit('/synthesis', { preserveState: true });
        }
    };

    if (!isDiscernment) {
        return null;
    }

    return (
        <div className="w-full px-3 py-2">
            <Card className="border-primary/20 bg-primary/5">
                <CardContent className="pt-4 pb-3 flex flex-col gap-3">
                    {/* Mode indicator header */}
                    <div className="flex items-center justify-between">
                        <span className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
                            Discernment Mode
                        </span>
                    </div>

                    {/* Toggle switch */}
                    <div className="flex items-center gap-2">
                        {/* Pastoral button */}
                        <Button
                            variant={mode === 'pastoral' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleModeChange('pastoral')}
                            className={`flex-1 text-xs ${
                                mode === 'pastoral'
                                    ? 'bg-indigo-600 hover:bg-indigo-700'
                                    : ''
                            }`}
                        >
                            Pastoral
                        </Button>

                        {/* Synthesis button */}
                        <Button
                            variant={mode === 'synthesis' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => handleModeChange('synthesis')}
                            className={`flex-1 text-xs ${
                                mode === 'synthesis'
                                    ? 'bg-indigo-600 hover:bg-indigo-700'
                                    : ''
                            }`}
                        >
                            Synthesis
                        </Button>
                    </div>

                    {/* Current mode indicator */}
                    <div className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground">
                        <span className="inline-block size-2 rounded-full bg-indigo-500 animate-pulse" />
                        <span>
                            Currently in{' '}
                            <strong className="text-foreground">
                                {mode === 'pastoral' ? 'Pastoral' : 'Synthesis'}
                            </strong>{' '}
                            mode
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

export default RoleSwitcher;
