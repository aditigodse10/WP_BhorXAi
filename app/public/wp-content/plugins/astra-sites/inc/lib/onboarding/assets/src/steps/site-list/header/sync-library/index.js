import React, { useState, useEffect } from 'react';
import { Toaster } from '@brainstormforce/starter-templates-components';
import Tooltip from '../../../../components/tooltip/tooltip';
import { __ } from '@wordpress/i18n';
import ICONS from '../../../../../icons';
import { useStateValue } from '../../../../store/store';
import {
	isSyncSuccess,
	isSyncUptoDate,
	fetchSitesPageCount,
	fetchPagedSites,
	fetchCategoriesAndTags,
} from './utils';
import './style.scss';
import { classNames, getStepIndex } from '../../../../utils/functions';

const SyncLibrary = () => {
	const [ { currentIndex, bgSyncInProgress, sitesSyncing }, dispatch ] =
		useStateValue();

	const [ syncState, setSyncState ] = useState( {
		updatedData: null,
		syncStatus: null,
	} );

	const { updatedData, syncStatus } = syncState;

	useEffect( () => {
		if ( sitesSyncing ) {
			window.onbeforeunload = () => {
				return true;
			};

			return () => {
				window.onbeforeunload = null;
			};
		}
	}, [ sitesSyncing ] );

	if (
		getStepIndex( 'page-builder' ) === currentIndex ||
		getStepIndex( 'classic-page-builder' ) === currentIndex
	) {
		return null;
	}
	if ( syncStatus === true && !! updatedData ) {
		const { sites, categories, categoriesAndTags } = updatedData;

		if ( !! sites && !! categories && !! categoriesAndTags ) {
			dispatch( {
				type: 'set',
				allSitesData: sites,
				allCategories: categories,
				allCategoriesAndTags: categoriesAndTags,
			} );
		}
		setSyncState( {
			...syncState,
			updatedData: null,
		} );
	}

	const handleClick = async ( event ) => {
		event.stopPropagation();

		if ( sitesSyncing || bgSyncInProgress ) {
			return;
		}

		dispatch( {
			type: 'set',
			sitesSyncing: true,
			syncPageCount: 0,
			syncPageInProgress: 0,
		} );

		const syncUptoDate = await isSyncUptoDate();
		if ( syncUptoDate ) {
			setSyncState( {
				updatedData: {
					allSitesData: null,
					categories: null,
					categoriesAndTags: null,
				},
				syncStatus: isSyncSuccess(),
			} );
			dispatch( {
				type: 'set',
				sitesSyncing: false,
			} );
		} else {
			const sites = await syncSites();
			const { categories, tags } = await fetchCategoriesAndTags();
			setSyncState( {
				updatedData: {
					allSitesData: sites,
					categories,
					categoriesAndTags: tags,
				},
				syncStatus: isSyncSuccess(),
			} );
			dispatch( {
				type: 'set',
				sitesSyncing: false,
			} );
		}
	};

	const syncSites = async () => {
		// const newData = await SyncStart();
		const pageCount = await fetchSitesPageCount();
		dispatch( {
			type: 'set',
			syncPageCount: pageCount,
		} );

		const sites = [];
		for ( let i = 0; i < pageCount; i++ ) {
			sites.push( await fetchPagedSites( i + 1 ) );
			dispatch( {
				type: 'set',
				syncPageInProgress: i + 1,
			} );
		}
		if ( sites.length > 0 ) {
			return sites;
		}
		return null;
	};

	return (
		<>
			<div
				className={ classNames(
					'relative st-sync-library',
					sitesSyncing && 'loading',
					bgSyncInProgress && 'cursor-not-allowed'
				) }
				onClick={ handleClick }
			>
				<Tooltip
					content={
						! bgSyncInProgress &&
						__( 'Sync Library', 'astra-sites' )
					}
				>
					<div className="inline-flex items-center justify-center">
						<span
							className={ classNames(
								bgSyncInProgress && 'opacity-50'
							) }
						>
							{ ICONS.sync }
						</span>
						{ bgSyncInProgress && (
							<span className="absolute bottom-[18%] left-1/2 -translate-x-1/2 translate-y-1/2 rounded bg-credit-warning pb-px px-1 pt-0 text-white shadow-sm text-[0.625rem] leading-[0.9375rem]">
								{ __( 'Syncing', 'astra-sites' ) }
							</span>
						) }
					</div>
				</Tooltip>
			</div>
			{ ! sitesSyncing && syncStatus === true && (
				<Toaster
					type="success"
					message={ __(
						'Library refreshed successfully',
						'astra-sites'
					) }
					autoHideDuration={ 5 }
					bottomRight={ true }
				/>
			) }
			{ ! sitesSyncing && syncStatus === false && (
				<Toaster
					type="error"
					message={ __( 'Library refreshed failed!', 'astra-sites' ) }
					autoHideDuration={ 5 }
					bottomRight={ true }
				/>
			) }
		</>
	);
};

export default SyncLibrary;
