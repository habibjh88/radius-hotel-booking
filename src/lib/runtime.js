/**
 * Extension runtime (ADR-015).
 *
 * Publishes the pieces an add-on (Radius Hotel Booking Pro, a client add-on)
 * needs on `window.rtbp`, so the add-on reuses this plugin's components and
 * API client instead of bundling its own copies. Add-on bundles map imports
 * to these globals through webpack externals (`@rtbp/ui` → `window.rtbp.ui`)
 * and extend the app with the `rtbp.*` filters from `@wordpress/hooks`.
 *
 * Nothing here contains add-on features — it only exposes shared building
 * blocks, which keeps the free plugin free of locked code.
 */
import * as hooks from '@wordpress/hooks';

import api from '@/api/client';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
	Card,
	CardContent,
	CardDescription,
	CardFooter,
	CardHeader,
	CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';

/**
 * Attach the runtime to `window.rtbp`. Runs once, before the app renders, so
 * add-on scripts (which depend on the admin handle) can use it immediately.
 *
 * @return {Object} The runtime.
 */
export function publishRuntime() {
	const runtime = {
		version: 1,
		hooks,
		ui: {
			Badge,
			Button,
			Card,
			CardContent,
			CardDescription,
			CardFooter,
			CardHeader,
			CardTitle,
			Input,
			Label,
			Separator,
			Switch,
		},
		lib: { api, cn },
	};

	window.rtbp = { ...( window.rtbp || {} ), ...runtime };

	return window.rtbp;
}
