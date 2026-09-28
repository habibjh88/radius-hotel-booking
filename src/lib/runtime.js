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
import React, { lazy } from 'react';
import * as hooks from '@wordpress/hooks';
import * as ReactQuery from '@tanstack/react-query';

import api from '@/api/client';
import * as format from '@/lib/format';
import * as sound from '@/lib/sound';
import * as status from '@/lib/status';
import { queryClient } from '@/lib/query-client';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import DateTime from '@/components/common/DateTime';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import MediaField from '@/components/common/MediaField';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';
import StatCard from '@/components/common/StatCard';
import { Field, FormSection } from '@/components/common/Form';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
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
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

// Heavier components are published lazily so they stay out of the initial
// bundle; they render inside the app's Suspense boundaries like any screen.
const ConfirmDialog = lazy( () =>
	import( '@/components/common/ConfirmDialog' )
);
const DataTable = lazy( () => import( '@/components/common/DataTable' ) );
const DateRangePicker = lazy( () =>
	import( '@/components/common/DateRangePicker' )
);

/**
 * Lazy stand-ins for every named export of a module, so a family of Radix
 * parts (Dialog, DialogContent, …) is published under its usual names but
 * loaded, as one chunk, the first time any of them renders. Eager, the four
 * families below would add about 220 KB to every page.
 *
 * @param {Function} load  Dynamic import of the module.
 * @param {string[]} names Export names.
 * @return {Object} Name => lazy component.
 */
function lazyParts( load, names ) {
	return Object.fromEntries(
		names.map( ( name ) => [
			name,
			lazy( () =>
				load().then( ( module ) => ( { default: module[ name ] } ) )
			),
		] )
	);
}

const DialogParts = lazyParts(
	() => import( '@/components/ui/dialog' ),
	[
		'Dialog',
		'DialogPortal',
		'DialogOverlay',
		'DialogTrigger',
		'DialogClose',
		'DialogContent',
		'DialogHeader',
		'DialogFooter',
		'DialogTitle',
		'DialogDescription',
	]
);

const DropdownMenuParts = lazyParts(
	() => import( '@/components/ui/dropdown-menu' ),
	[
		'DropdownMenu',
		'DropdownMenuTrigger',
		'DropdownMenuContent',
		'DropdownMenuItem',
		'DropdownMenuCheckboxItem',
		'DropdownMenuRadioItem',
		'DropdownMenuLabel',
		'DropdownMenuSeparator',
		'DropdownMenuShortcut',
		'DropdownMenuGroup',
		'DropdownMenuPortal',
		'DropdownMenuSub',
		'DropdownMenuSubContent',
		'DropdownMenuSubTrigger',
		'DropdownMenuRadioGroup',
	]
);

const SelectParts = lazyParts(
	() => import( '@/components/ui/select' ),
	[
		'Select',
		'SelectGroup',
		'SelectValue',
		'SelectTrigger',
		'SelectContent',
		'SelectLabel',
		'SelectItem',
		'SelectSeparator',
		'SelectScrollUpButton',
		'SelectScrollDownButton',
	]
);

const TabsParts = lazyParts(
	() => import( '@/components/ui/tabs' ),
	[ 'Tabs', 'TabsList', 'TabsTrigger', 'TabsContent' ]
);

/**
 * Attach the runtime to `window.rtbp`. Runs once, before the app renders, so
 * add-on scripts (which depend on the admin handle) can use it immediately.
 *
 * @return {Object} The runtime.
 */
export function publishRuntime() {
	const runtime = {
		version: 1,
		// WordPress's shared React (the same object as window.React). Builds
		// made with @wordpress/scripts get it through the `react` script
		// dependency anyway; this is for bundles made any other way.
		React,
		hooks,
		ui: {
			...DialogParts,
			...DropdownMenuParts,
			...SelectParts,
			...TabsParts,
			Badge,
			ConfirmDialog,
			DataTable,
			DateRangePicker,
			Field,
			DateTime,
			FilterTabs,
			Money,
			FormSection,
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
			StatusBadge,
			Switch,
			EmptyState,
			MediaField,
			Panel,
			SegmentedControl,
			SettingsSection,
			Skeleton,
			StatCard,
			Textarea,
		},
		lib: {
			api,
			cn,
			format,
			// The new-booking sound: sound.playNotificationSound( url ).
			sound,
			status,
			toast,
			toastError,
			queryClient,
			// Put buttons in the top bar from an add-on screen.
			usePageActions,
			// react-hook-form + zod are heavy, so they load on demand:
			// `const { useZodForm, applyServerErrors, z } = await rtbp.lib.loadForms()`,
			// typically inside the add-on's lazy screen module.
			loadForms: () => import( '@/lib/forms' ),
		},
		// Add-ons import '@tanstack/react-query' as an external mapped here, so
		// they share this instance and its cache (one QueryClientProvider).
		ReactQuery,
	};

	window.rtbp = { ...( window.rtbp || {} ), ...runtime };

	return window.rtbp;
}
