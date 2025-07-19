import React, { useEffect } from 'react';
import Lottie from 'react-lottie-player';
import { __, sprintf } from '@wordpress/i18n';
import PreviousStepLink from '../../components/util/previous-step-link/index';
import DefaultStep from '../../components/default-step/index';
import ImportLoader from '../../components/import-steps/import-loader';
import ErrorScreen from '../../components/error/index';
import { useStateValue } from '../../store/store';
import lottieJson from '../../../images/website-building.json';
import ICONS from '../../../icons';
import sseImport from './sse-import';
import {
	installAstra,
	saveTypography,
	setSiteLogo,
	setColorPalettes,
	divideIntoChunks,
	checkRequiredPlugins,
	generateAnalyticsLead,
} from './import-utils';
const { reportError } = starterTemplates;
const successMessageDelay = 8000; // 8 seconds delay for fully assets load.

import './style.scss';

const ImportSite = () => {
	const storedState = useStateValue();
	const [
		{
			importStart,
			importEnd,
			importPercent,
			templateResponse,
			reset,
			themeStatus,
			importError,
			siteLogo,
			activePalette,
			typography,
			customizerImportFlag,
			widgetImportFlag,
			contentImportFlag,
			themeActivateFlag,
			requiredPluginsDone,
			requiredPlugins,
			notInstalledList,
			notActivatedList,
			tryAgainCount,
			xmlImportDone,
			templateId,
			selectedTemplateType,
			builder,
			pluginInstallationAttempts,
		},
		dispatch,
	] = storedState;

	let percentage = importPercent;

	/**
	 *
	 * @param {string} primary   Primary text for the error.
	 * @param {string} secondary Secondary text for the error.
	 * @param {string} text      Text received from the AJAX call.
	 * @param {string} code      Error code received from the AJAX call.
	 * @param {string} solution  Solution provided for the current error.
	 * @param {string} stack
	 */
	const report = (
		primary = '',
		secondary = '',
		text = '',
		code = '',
		solution = '',
		stack = ''
	) => {
		dispatch( {
			type: 'set',
			importError: true,
			importErrorMessages: {
				primaryText: primary,
				secondaryText: secondary,
				errorCode: code,
				errorText: text,
				solutionText: solution,
				tryAgain: true,
			},
		} );

		localStorage.removeItem( 'st-import-start' );
		localStorage.removeItem( 'st-import-end' );

		sendErrorReport(
			primary,
			secondary,
			text,
			code,
			solution,
			stack,
			tryAgainCount
		);
	};

	const sendErrorReport = (
		primary = '',
		secondary = '',
		text = '',
		code = '',
		solution = '',
		stack = ''
	) => {
		const error = JSON.stringify( {
			primaryText: primary,
			secondaryText: secondary,
			errorCode: code,
			errorText: text,
			solutionText: solution,
			tryAgain: true,
			stack,
			tryAgainCount,
		} );

		if ( tryAgainCount >= 2 ) {
			generateAnalyticsLead( tryAgainCount, false, {
				id: templateId,
				page_builder: builder,
				template_type: selectedTemplateType,
				error,
			} );
		}
		if ( ! reportError ) {
			return;
		}
		const reportErr = new FormData();
		reportErr.append( 'action', 'astra-sites-report_error' );
		reportErr.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );
		reportErr.append( 'type', 'classic' );
		reportErr.append( 'page_builder', builder );
		reportErr.append( 'template_type', selectedTemplateType );

		reportErr.append( 'error', error );
		reportErr.append( 'id', templateResponse.id );
		reportErr.append( 'plugins', JSON.stringify( requiredPlugins ) );
		fetch( ajaxurl, {
			method: 'post',
			body: reportErr,
		} );
	};

	/**
	 * Start Import Part 1.
	 */
	const importPart1 = async () => {
		let resetStatus = false;
		let cfStatus = false;
		let wooCARStatus = false;
		let latepointStatus = false;
		let formsStatus = false;
		let customizerStatus = false;
		let spectraStatus = false;
		let sureCartStatus = false;

		resetStatus = await resetOldSite();

		if ( resetStatus ) {
			cfStatus = await importCartflowsFlows();
		}

		if ( cfStatus ) {
			wooCARStatus = await importCartAbandonmentRecovery();
		}

		if ( wooCARStatus ) {
			latepointStatus = await importLatepointTables();
		}

		if ( latepointStatus ) {
			formsStatus = await importForms();
		}

		if ( formsStatus ) {
			customizerStatus = await importCustomizerJson();
		}

		if ( customizerStatus ) {
			spectraStatus = await importSpectraSettings();
		}

		if ( spectraStatus ) {
			sureCartStatus = await importSureCartSettings();
		}

		if ( sureCartStatus ) {
			await importSiteContent();
		}
	};

	/**
	 * Start Import Part 2.
	 */
	const importPart2 = async () => {
		let optionsStatus = false;
		let widgetStatus = false;
		let customizationsStatus = false;
		let finalStepStatus = false;

		optionsStatus = await importSiteOptions();

		if ( optionsStatus ) {
			widgetStatus = await importWidgets();
		}

		if ( widgetStatus ) {
			customizationsStatus = await customizeWebsite();
		}

		if ( customizationsStatus ) {
			finalStepStatus = await importDone();
		}

		if ( finalStepStatus ) {
			generateAnalyticsLead( tryAgainCount, true, {
				id: templateId,
				page_builder: builder,
				template_type: selectedTemplateType,
			} );
		}
	};

	/**
	 * ========================================
	 * PLUGIN INSTALLATION QUEUE SYSTEM
	 * ========================================
	 *
	 *
	 * This system handles sequential plugin installation to avoid:
	 * - Race conditions in state management
	 * - Server overload from simultaneous requests
	 * - Plugin installation conflicts
	 */

	// Queue state variables
	const [ isQueueProcessing, setIsQueueProcessing ] = React.useState( false );

	/**
	 * Install Required plugins using queue system
	 */
	const installRequiredPlugins = async () => {
		// Check if there are plugins to install.
		if ( notInstalledList.length <= 0 ) {
			return;
		}

		// Update progress.
		percentage += 2;
		dispatch( {
			type: 'set',
			importStatus: __( 'Preparing plugin installation…', 'astra-sites' ),
			importPercent: percentage,
		} );

		await processPluginQueue();
	};

	/**
	 * Process the plugin installation queue sequentially
	 *
	 * HOW IT WORKS:
	 * 1. Takes first plugin from queue
	 * 2. Installs it via Ajax
	 * 3. Updates state (moves from notInstalledList to notActivatedList)
	 * 4. Removes plugin from queue
	 * 5. Repeats until queue is empty OR any plugin fails
	 */
	const processPluginQueue = async () => {
		// Prevent multiple queue processing.
		if ( isQueueProcessing ) {
			return;
		}

		// Check if queue has plugins
		if ( notInstalledList.length === 0 ) {
			return;
		}
		percentage += 2;
		setIsQueueProcessing( true );

		// Process each plugin in the queue
		while ( notInstalledList.length > 0 ) {
			const currentPlugin = notInstalledList[ 0 ]; // Get first plugin

			// Update UI with current progress
			dispatch( {
				type: 'set',
				importStatus: sprintf(
					// translators: Installing Plugin Name.
					__( 'Installing plugin %1$s', 'astra-sites' ),
					currentPlugin.name
				),
				importPercent: percentage,
			} );

			try {
				// Install the current plugin
				const installResult = await installSinglePlugin(
					currentPlugin
				);

				// Check if installation was successful
				if ( installResult === false ) {
					// Installation failed - stop the entire queue processing
					setIsQueueProcessing( false );
					return; // Exit immediately - no further plugins should be processed
				}

				// Remove successfully installed plugin from queue
				notInstalledList.shift();
			} catch ( error ) {
				// Remove failed plugin from queue but STOP processing
				notInstalledList.shift();

				// Report the error
				report(
					sprintf(
						// translators: Installing Failed Plugin Name.
						__( 'Failed to install plugin: %s', 'astra-sites' ),
						currentPlugin.name
					),
					'',
					error,
					true
				);

				// CRITICAL FIX: Stop queue processing immediately on any failure
				setIsQueueProcessing( false );
				return; // Exit immediately - no further plugins should be processed
			}
		}

		// Queue processing completed successfully
		setIsQueueProcessing( false );
	};

	/**
	 * Install a single plugin and update state properly
	 *
	 * @param {Object} plugin - Plugin object to install
	 */
	const installSinglePlugin = async ( plugin ) => {
		try {
			// Prepare AJAX request data using FormData
			const formData = new FormData();
			formData.append( 'action', 'astra_sites_install_plugin' );
			formData.append( 'slug', plugin.slug );
			formData.append( 'name', plugin.name );
			formData.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

			// Include init parameter if provided
			if ( plugin.init ) {
				formData.append( 'init', plugin.init );
			}

			// Make AJAX call using fetch
			const fetchResponse = await fetch( ajaxurl, {
				method: 'POST',
				body: formData,
			} );

			// Parse response text as JSON
			const responseText = await fetchResponse.text();
			let response;

			try {
				response = JSON.parse( responseText );
			} catch ( parseError ) {
				// Report JSON parse error
				report(
					sprintf(
						// translators: Installing Failed Plugin Name.
						__( 'Failed to install plugin: %s', 'astra-sites' ),
						plugin.name
					),
					__(
						'Invalid response from server during plugin installation.',
						'astra-sites'
					),
					parseError.message ||
						__( 'JSON parse error', 'astra-sites' ),
					'json_parse_error',
					__(
						'Please try again. If the problem persists, check server logs for more details.',
						'astra-sites'
					),
					responseText
				);
				return false;
			}

			// Check if installation was unsuccessful
			if ( ! response.success ) {
				const errorMessage =
					response.data?.message ||
					response.message ||
					__( 'Plugin installation failed', 'astra-sites' );
				const errorCode = response.data?.code || 'installation_failed';

				// Report installation failure
				report(
					sprintf(
						// translators: Installing Failed Plugin Name.
						__( 'Failed to install plugin: %s', 'astra-sites' ),
						plugin.name
					),
					'',
					errorMessage,
					errorCode,
					sprintf(
						// translators: Resolution html
						__(
							'<a href="%1$s">Read article</a> to resolve the issue and continue importing template.',
							'astra-sites'
						),
						'https://wpastra.com/docs/enable-debugging-in-wordpress/#how-to-use-debugging'
					),
					JSON.stringify( response )
				);
				return false;
			}

			// Plugin installed successfully - update state
			updatePluginState( plugin, response );
			return response;
		} catch ( error ) {
			// Determine error type and message
			let errorMessage =
				error.message ||
				__( 'Unknown installation error', 'astra-sites' );
			let errorCode = 'unknown_error';
			let solution = sprintf(
				// translators: Resolution html
				__(
					'<a href="%1$s">Read article</a> to resolve the issue and continue importing template.',
					'astra-sites'
				),
				'https://wpastra.com/docs/enable-debugging-in-wordpress/#how-to-use-debugging'
			);

			// Handle network errors
			if (
				error.name === 'TypeError' &&
				error.message.includes( 'fetch' )
			) {
				errorMessage = __(
					'Network error occurred during plugin installation.',
					'astra-sites'
				);
				errorCode = 'network_error';
				solution = __(
					'Please check your internet connection and try again.',
					'astra-sites'
				);
			}

			// Report the error
			report(
				sprintf(
					// translators: Installing Failed Plugin Name.
					__( 'Failed to install plugin: %s', 'astra-sites' ),
					plugin.name
				),
				'',
				errorMessage,
				errorCode,
				solution,
				error.stack || error.toString()
			);

			return false;
		}
	};

	/**
	 * Update plugin state after successful installation
	 *
	 * CRITICAL: This function properly handles the state transition:
	 * - Adds plugin to notActivatedList (for activation)
	 * - Removes plugin from notInstalledList (no longer needs installation)
	 *
	 * @param {Object} plugin   - Original plugin object
	 * @param {Object} response - API response from installation
	 */
	const updatePluginState = ( plugin, response ) => {
		// Get current state
		const currentState = storedState[ 0 ];
		const currentNotActivatedList = currentState.notActivatedList || [];
		const currentNotInstalledList = currentState.notInstalledList || [];

		// Prepare plugin object for activation list
		const pluginForActivation = {
			...plugin,
			init: response.data?.plugin?.file || plugin.init, // Use file path from response
		};

		// Create updated lists
		const updatedNotActivatedList = [
			...currentNotActivatedList,
			pluginForActivation,
		];
		const updatedNotInstalledList = currentNotInstalledList.filter(
			( installedPlugin ) => installedPlugin.slug !== plugin.slug
		);

		// Update state atomically (both lists in single dispatch)
		dispatch( {
			type: 'set',
			notActivatedList: updatedNotActivatedList,
			notInstalledList: updatedNotInstalledList,
		} );
	};

	/**
	 * Activate Plugin
	 *
	 * @param {Object} plugin
	 */
	const activatePlugin = ( plugin ) => {
		percentage += 2;
		dispatch( {
			type: 'set',
			importStatus: sprintf(
				// translators: Plugin Name.
				__( 'Activating %1$s plugin.', 'astra-sites' ),
				plugin.name
			),
			importPercent: percentage,
		} );

		const activatePluginOptions = new FormData();
		activatePluginOptions.append(
			'action',
			'astra-sites-required_plugin_activate'
		);
		activatePluginOptions.append( 'init', plugin.init );
		activatePluginOptions.append(
			'_ajax_nonce',
			astraSitesVars?._ajax_nonce
		);
		activatePluginOptions.append( 'slug', plugin.slug );
		fetch( ajaxurl, {
			method: 'post',
			body: activatePluginOptions,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				let cloneResponse = [];
				let errorReported = false;
				try {
					const response = JSON.parse( text );
					cloneResponse = response;
					if ( response.success ) {
						// Check if this is a deprioritization response
						let deprioritizeStatus = false;
						if (
							response.data &&
							response.data.status === 'deprioritize'
						) {
							deprioritizeStatus = true;

							// Add to deferred queue
							setDeferredPlugins( ( prev ) => {
								const exists = prev.some(
									( p ) => p.slug === plugin.slug
								);
								if ( ! exists ) {
									return [
										...prev,
										{
											...plugin,
											deferReason: response.data.reason,
											retryAfter:
												response.data.retry_after,
											dependency:
												response.data.dependency,
										},
									];
								}
								return prev;
							} );
						}

						// Remove from active processing list
						const notActivatedPluginList = notActivatedList;
						notActivatedPluginList.forEach(
							( singlePlugin, index ) => {
								if ( singlePlugin.slug === plugin.slug ) {
									notActivatedPluginList.splice( index, 1 );
								}
							}
						);
						dispatch( {
							type: 'set',
							notActivatedList: notActivatedPluginList,
						} );

						if ( deprioritizeStatus ) {
							dispatch( {
								type: 'set',
								importStatus: sprintf(
									// translators: Plugin Name.
									__(
										'%1$s deferred (requires WooCommerce).',
										'astra-sites'
									),
									plugin.name
								),
								importPercent: percentage,
							} );
						} else {
							percentage += 2;
							dispatch( {
								type: 'set',
								importStatus: sprintf(
									// translators: Plugin Name.
									__( '%1$s activated.', 'astra-sites' ),
									plugin.name
								),
								importPercent: percentage,
							} );
						}
					}
				} catch ( error ) {
					report(
						sprintf(
							// translators: Plugin name.
							__(
								`JSON_Error: Could not activate the required plugin - %1$s.`,
								'astra-sites'
							),
							plugin.name
						),
						'',
						error,
						'',
						sprintf(
							// translators: Support article URL.
							__(
								'<a href="%1$s">Read article</a> to resolve the issue and continue importing template.',
								'astra-sites'
							),
							'https://wpastra.com/docs/enable-debugging-in-wordpress/#how-to-use-debugging'
						),
						text
					);

					errorReported = true;
				}

				if ( ! cloneResponse.success && errorReported === false ) {
					throw cloneResponse;
				}
			} )
			.catch( ( error ) => {
				dispatch( {
					type: 'set',
					pluginInstallationAttempts: pluginInstallationAttempts + 1,
				} );
				report(
					sprintf(
						// translators: Plugin name.
						__(
							`Could not activate the required plugin - %1$s.`,
							'astra-sites'
						),
						plugin.name
					),
					'',
					error?.data?.message,
					'',
					sprintf(
						// translators: Support article URL.
						__(
							'<a href="%1$s">Read article</a> to resolve the issue and continue importing template.',
							'astra-sites'
						),
						'https://wpastra.com/docs/enable-debugging-in-wordpress/#how-to-use-debugging'
					),
					error
				);
			} );
	};

	/**
	 * 1. Reset.
	 * The following steps are covered here.
	 * 		1. Settings backup file store.
	 * 		2. Reset Customizer
	 * 		3. Reset Site Options
	 * 		4. Reset Widgets
	 * 		5. Reset Forms and Terms
	 * 		6. Reset all posts
	 */
	const resetOldSite = async () => {
		if ( ! reset ) {
			return true;
		}
		percentage += 2;
		dispatch( {
			type: 'set',
			importStatus: __( 'Reseting site.', 'astra-sites' ),
			importPercent: percentage,
		} );

		let backupFileStatus = false;
		let resetCustomizerStatus = false;
		let resetWidgetStatus = false;
		let resetOptionsStatus = false;
		let reseteTermsStatus = false;
		let resetPostsStatus = false;

		/**
		 * Settings backup file store.
		 */
		backupFileStatus = await performSettingsBackup();

		/**
		 * Reset Customizer.
		 */
		if ( backupFileStatus ) {
			resetCustomizerStatus = await performResetCustomizer();
		}

		/**
		 * Reset Site Options.
		 */
		if ( resetCustomizerStatus ) {
			resetOptionsStatus = await performResetSiteOptions();
		}

		/**
		 * Reset Widgets.
		 */
		if ( resetOptionsStatus ) {
			resetWidgetStatus = await performResetWidget();
		}

		/**
		 * Reset Terms, Forms.
		 */
		if ( resetWidgetStatus ) {
			reseteTermsStatus = await performResetTermsAndForms();
		}

		/**
		 * Reset Posts.
		 */
		if ( reseteTermsStatus ) {
			resetPostsStatus = await performResetPosts();
		}

		if (
			! (
				resetCustomizerStatus &&
				resetOptionsStatus &&
				resetWidgetStatus &&
				reseteTermsStatus &&
				resetPostsStatus
			)
		) {
			return false;
		}

		percentage += 10;
		dispatch( {
			type: 'set',
			importPercent: percentage >= 50 ? 50 : percentage,
			importStatus: __( 'Reset for old website is done.', 'astra-sites' ),
		} );

		return true;
	};

	/**
	 * Reset a chunk of posts.
	 *
	 * @param {Object} chunk
	 */
	const performPostsReset = async ( chunk ) => {
		const data = new FormData();
		data.append( 'action', 'astra-sites-get_deleted_post_ids' );
		data.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		dispatch( {
			type: 'set',
			importStatus: __( `Resetting posts.`, 'astra-sites' ),
		} );

		const formOption = new FormData();
		formOption.append( 'action', 'astra-sites-reset_posts' );
		formOption.append( 'ids', JSON.stringify( chunk ) );
		formOption.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		await fetch( ajaxurl, {
			method: 'post',
			body: formOption,
		} )
			.then( ( resp ) => resp.text() )
			.then( ( text ) => {
				let cloneData = [];
				let errorReported = false;
				try {
					const result = JSON.parse( text );
					cloneData = result;
					if ( result.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 50 ? 50 : percentage,
						} );
					} else {
						throw result;
					}
				} catch ( error ) {
					report(
						__( 'Resetting posts failed.', 'astra-sites' ),
						'',
						error,
						'',
						'',
						text
					);

					errorReported = true;
					return false;
				}

				if ( ! cloneData.success && errorReported === false ) {
					throw cloneData.data;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Resetting posts failed.', 'astra-sites' ),
					'',
					error?.message,
					'',
					'',
					error
				);
				return false;
			} );
		return true;
	};

	/**
	 * 1.0 Perform Settings backup file stored.
	 */
	const performSettingsBackup = async () => {
		dispatch( {
			type: 'set',
			importStatus: __( 'Taking settings backup.', 'astra-sites' ),
		} );

		const customizerContent = new FormData();
		customizerContent.append( 'action', 'astra-sites-backup_settings' );
		customizerContent.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: customizerContent,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				const response = JSON.parse( text );
				if ( response.success ) {
					percentage += 2;
					dispatch( {
						type: 'set',
						importPercent: percentage,
					} );
					return true;
				}
				throw response.data;
			} )
			.catch( ( error ) => {
				report(
					__( 'Taking settings backup failed.', 'astra-sites' ),
					'',
					error?.message,
					'',
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 1.1 Perform Reset for Customizer.
	 */
	const performResetCustomizer = async () => {
		dispatch( {
			type: 'set',
			importStatus: __( 'Resetting customizer.', 'astra-sites' ),
		} );

		const customizerContent = new FormData();
		customizerContent.append(
			'action',
			'astra-sites-reset_customizer_data'
		);
		customizerContent.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: customizerContent,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const response = JSON.parse( text );
					if ( response.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw response.data;
				} catch ( error ) {
					report(
						__( 'Resetting customizer failed.', 'astra-sites' ),
						'',
						error?.message,
						'',
						'',
						text
					);

					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Resetting customizer failed.', 'astra-sites' ),
					'',
					error?.message,
					'',
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 1.2 Perform reset Site options
	 */
	const performResetSiteOptions = async () => {
		dispatch( {
			type: 'set',
			importStatus: __( 'Resetting site options.', 'astra-sites' ),
		} );

		const siteOptions = new FormData();
		siteOptions.append( 'action', 'astra-sites-reset_site_options' );
		siteOptions.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: siteOptions,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__( 'Resetting site options Failed.', 'astra-sites' ),
						'',
						error?.message,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Resetting site options Failed.', 'astra-sites' ),
					'',
					error?.message,
					'',
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 1.3 Perform Reset for Widgets
	 */
	const performResetWidget = async () => {
		const widgets = new FormData();
		widgets.append( 'action', 'astra-sites-reset_widgets_data' );
		widgets.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		dispatch( {
			type: 'set',
			importStatus: __( 'Resetting widgets.', 'astra-sites' ),
		} );
		const status = await fetch( ajaxurl, {
			method: 'post',
			body: widgets,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const response = JSON.parse( text );
					if ( response.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw response.data;
				} catch ( error ) {
					report(
						__(
							'Resetting widgets JSON parse failed.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Resetting widgets failed.', 'astra-sites' ),
					'',
					error,
					'',
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 1.4 Reset Terms and Forms.
	 */
	const performResetTermsAndForms = async () => {
		const formOption = new FormData();
		formOption.append( 'action', 'astra-sites-reset_terms_and_forms' );
		formOption.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		dispatch( {
			type: 'set',
			importStatus: __( 'Resetting terms and forms.', 'astra-sites' ),
		} );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: formOption,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const response = JSON.parse( text );
					if ( response.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw response.data;
				} catch ( error ) {
					report(
						__(
							'Resetting terms and forms failed.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Resetting terms and forms failed.', 'astra-sites' ),
					'',
					error?.message,
					'',
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 1.5 Reset Posts.
	 */
	const performResetPosts = async () => {
		const data = new FormData();
		data.append( 'action', 'astra-sites-get_deleted_post_ids' );
		data.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		dispatch( {
			type: 'set',
			importStatus: __( 'Gathering posts for deletions.', 'astra-sites' ),
		} );

		let err = '';

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: data,
		} )
			.then( ( response ) => response.json() )
			.then( async ( response ) => {
				if ( response.success ) {
					const chunkArray = divideIntoChunks( 10, response.data );
					if ( chunkArray.length > 0 ) {
						for (
							let index = 0;
							index < chunkArray.length;
							index++
						) {
							await performPostsReset( chunkArray[ index ] );
						}
					}
					return true;
				}
				err = response;
				return false;
			} );

		if ( status ) {
			dispatch( {
				type: 'set',
				importStatus: __( 'Resetting posts done.', 'astra-sites' ),
			} );
		} else {
			report( __( 'Resetting posts failed.', 'astra-sites' ), '', err );
		}
		return status;
	};

	/**
	 * 2. Import CartFlows Flows.
	 */
	const importCartflowsFlows = async () => {
		const cartflowsUrl =
			encodeURI( templateResponse[ 'astra-site-cartflows-path' ] ) || '';

		if ( '' === cartflowsUrl || 'null' === cartflowsUrl ) {
			return true;
		}

		dispatch( {
			type: 'set',
			importStatus: __( 'Importing CartFlows flows.', 'astra-sites' ),
		} );

		const flows = new FormData();
		flows.append( 'action', 'astra-sites-import-cartflows' );
		flows.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: flows,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing CartFlows flows failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing CartFlows flows Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 2. Import Cart Abandonment Recovery data.
	 */
	const importCartAbandonmentRecovery = async () => {
		const wooCARUrl = encodeURI(
			templateResponse?.[ 'astra-site-cart-abandonment-recovery-path' ] ||
				''
		);

		if ( '' === wooCARUrl || 'null' === wooCARUrl ) {
			return true;
		}

		dispatch( {
			type: 'set',
			importStatus: __(
				'Importing Cart Abandonment Recovery data.',
				'astra-sites'
			),
		} );

		const bodyData = new FormData();
		bodyData.append(
			'action',
			'astra-sites-import-cart-abandonment-recovery'
		);
		bodyData.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: bodyData,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Cart Abandonment Recovery data failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__(
						'Importing Cart Abandonment Recovery data Failed.',
						'astra-sites'
					),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 3. Import LatePoint Tables.
	 */
	const importLatepointTables = async () => {
		const latepointUrl =
			encodeURI( templateResponse[ 'astra-site-latepoint-path' ] ) || '';

		if ( '' === latepointUrl || 'null' === latepointUrl ) {
			return true;
		}

		dispatch( {
			type: 'set',
			importStatus: __( 'Importing LatePoint data.', 'astra-sites' ),
		} );

		const bodyData = new FormData();
		bodyData.append( 'action', 'astra-sites-import-latepoint' );
		bodyData.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: bodyData,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing LatePoint data failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing LatePoint data Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 3. Import WPForms.
	 */
	const importForms = async () => {
		const wpformsUrl =
			encodeURI( templateResponse[ 'astra-site-wpforms-path' ] ) || '';

		if ( '' === wpformsUrl || 'null' === wpformsUrl ) {
			return true;
		}

		dispatch( {
			type: 'set',
			importStatus: __( 'Importing forms.', 'astra-sites' ),
		} );

		const flows = new FormData();
		flows.append( 'action', 'astra-sites-import-wpforms' );
		flows.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: flows,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 60 ? 60 : percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing forms failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing forms Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 4. Import Customizer JSON.
	 */
	const importCustomizerJson = async () => {
		if ( ! customizerImportFlag ) {
			percentage += 5;
			dispatch( {
				type: 'set',
				importPercent: percentage >= 65 ? 65 : percentage,
			} );
			return true;
		}
		dispatch( {
			type: 'set',
			importStatus: __( 'Importing forms.', 'astra-sites' ),
		} );

		const forms = new FormData();
		forms.append( 'action', 'astra-sites-import_customizer_settings' );
		forms.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: forms,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 5;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 65 ? 65 : percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Customizer failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Customizer Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );

		return status;
	};

	/**
	 * 5. Import Site Comtent XML.
	 */
	const importSiteContent = async () => {
		if ( ! contentImportFlag ) {
			percentage += 20;
			dispatch( {
				type: 'set',
				importPercent: percentage >= 80 ? 80 : percentage,
				xmlImportDone: true,
			} );
			return true;
		}

		const wxrUrl =
			encodeURI( templateResponse[ 'astra-site-wxr-path' ] ) || '';
		if ( 'null' === wxrUrl || '' === wxrUrl ) {
			const errorTxt = __(
				'The XML URL for the site content is empty.',
				'astra-sites'
			);
			report(
				__( 'Importing Site Content Failed', 'astra-sites' ),
				'',
				errorTxt,
				'',
				astraSitesVars?.support_text,
				wxrUrl
			);
			return false;
		}

		dispatch( {
			type: 'set',
			importStatus: __( 'Importing Site Content.', 'astra-sites' ),
		} );

		const content = new FormData();
		content.append( 'action', 'astra-sites-import_prepare_xml' );
		content.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: content,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					percentage += 2;
					dispatch( {
						type: 'set',
						importPercent: percentage >= 80 ? 80 : percentage,
					} );
					if ( false === data.success ) {
						const errorMsg = data.data.error || data.data;
						throw errorMsg;
					} else {
						importXML( data.data );
					}
					return true;
				} catch ( error ) {
					report(
						__(
							'Importing Site Content failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Site Content Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );

		return status;
	};

	/**
	 * 6. Import Spectra Settings.
	 */
	const importSpectraSettings = async () => {
		const spectraSettings =
			templateResponse[ 'astra-site-spectra-options' ] || '';

		if ( '' === spectraSettings || 'null' === spectraSettings ) {
			return true;
		}

		dispatch( {
			type: 'set',
			importStatus: __( 'Importing Spectra Settings.', 'astra-sites' ),
		} );

		const spectra = new FormData();
		spectra.append( 'action', 'astra-sites-import_spectra_settings' );
		spectra.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: spectra,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 75 ? 75 : percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Spectra Settings failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Spectra Settings Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 7. Import Surecart Settings.
	 */
	const importSureCartSettings = async () => {
		const sourceID =
			templateResponse?.[ 'astra-site-surecart-settings' ]?.id || '';
		const sourceCurrency =
			templateResponse?.[ 'astra-site-surecart-settings' ]?.currency ||
			'usd';
		if ( '' === sourceID || 'null' === sourceID ) {
			return true;
		}
		const surecart = new FormData();
		surecart.append( 'action', 'astra-sites-import_surecart_settings' );
		surecart.append( 'source_id', sourceID );
		surecart.append( 'source_currency', sourceCurrency );
		surecart.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: surecart,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 2;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 75 ? 75 : percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Surecart Settings failed.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Surecart Settings Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * Imports XML using EventSource.
	 *
	 * @param {JSON} data JSON object for all the content in XML
	 */
	const importXML = ( data ) => {
		// Import XML though Event Source.
		sseImport.data = data;
		sseImport.render( dispatch, percentage );

		const evtSource = new EventSource( sseImport.data.url );
		evtSource.onmessage = ( message ) => {
			const eventData = JSON.parse( message.data );
			switch ( eventData.action ) {
				case 'updateDelta':
					sseImport.updateDelta( eventData.type, eventData.delta );
					break;

				case 'complete':
					if ( false === eventData.error ) {
						evtSource.close();
						dispatch( {
							type: 'set',
							xmlImportDone: true,
						} );
					} else {
						report(
							astraSitesVars?.xml_import_interrupted_primary,
							'',
							astraSitesVars?.xml_import_interrupted_error,
							'',
							astraSitesVars?.xml_import_interrupted_secondary
						);
					}
					break;
			}
		};

		evtSource.onerror = ( error ) => {
			if ( ! ( error && error?.isTrusted ) ) {
				evtSource.close();
				report(
					__(
						'Importing Site Content Failed. - Import Process Interrupted',
						'astra-sites'
					),
					'',
					error
				);
			}
		};

		evtSource.addEventListener( 'log', function ( message ) {
			const eventLogData = JSON.parse( message.data );
			let importMessage = eventLogData.message || '';
			if ( importMessage && 'info' === eventLogData.level ) {
				importMessage = importMessage.replace( /"/g, function () {
					return '';
				} );
			}

			dispatch( {
				type: 'set',
				importStatus: sprintf(
					// translators: Response importMessage
					__( 'Importing - %1$s', 'astra-sites' ),
					importMessage
				),
			} );
		} );
	};

	/**
	 * 6. Import Site Option table values.
	 */
	const importSiteOptions = async () => {
		dispatch( {
			type: 'set',
			importStatus: __( 'Importing Site Options.', 'astra-sites' ),
		} );

		const siteOptions = new FormData();
		siteOptions.append( 'action', 'astra-sites-import_options' );
		siteOptions.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: siteOptions,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						percentage += 5;
						dispatch( {
							type: 'set',
							importPercent: percentage >= 90 ? 90 : percentage,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Site Options failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Site Options Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );

		return status;
	};

	/**
	 * 7. Import Site Widgets.
	 */
	const importWidgets = async () => {
		if ( ! widgetImportFlag ) {
			dispatch( {
				type: 'set',
				importPercent: 90,
			} );
			return true;
		}
		dispatch( {
			type: 'set',
			importStatus: __( 'Importing Widgets.', 'astra-sites' ),
		} );

		const widgetsData = templateResponse[ 'astra-site-widgets-data' ] || '';

		const widgets = new FormData();
		widgets.append( 'action', 'astra-sites-import_widgets' );
		widgets.append( 'widgets_data', widgetsData );
		widgets.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: widgets,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						dispatch( {
							type: 'set',
							importPercent: 90,
						} );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Importing Widgets failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Importing Widgets Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );
		return status;
	};

	/**
	 * 8. Update the website as per the customizations selected by the user.
	 * The following steps are covered here.
	 * 		a. Update Logo
	 * 		b. Update Color Palette
	 * 		c. Update Typography
	 */
	const customizeWebsite = async () => {
		await setSiteLogo( siteLogo );
		await setColorPalettes( JSON.stringify( activePalette ) );
		await saveTypography( typography );
		return true;
	};

	/**
	 * 9. Final setup - Invoking Batch process.
	 */
	const importDone = async () => {
		dispatch( {
			type: 'set',
			importStatus: __( 'Final finishings.', 'astra-sites' ),
		} );

		const finalSteps = new FormData();
		finalSteps.append( 'action', 'astra-sites-import_end' );
		finalSteps.append( '_ajax_nonce', astraSitesVars?._ajax_nonce );

		const status = await fetch( ajaxurl, {
			method: 'post',
			body: finalSteps,
		} )
			.then( ( response ) => response.text() )
			.then( ( text ) => {
				try {
					const data = JSON.parse( text );
					if ( data.success ) {
						localStorage.setItem( 'st-import-end', +new Date() );
						setTimeout( function () {
							dispatch( {
								type: 'set',
								importPercent: 100,
								importEnd: true,
							} );
						}, successMessageDelay );
						return true;
					}
					throw data.data;
				} catch ( error ) {
					report(
						__(
							'Final finishings failed due to parse JSON error.',
							'astra-sites'
						),
						'',
						error,
						'',
						'',
						text
					);
					setTimeout( function () {
						dispatch( {
							type: 'set',
							importPercent: 100,
							importEnd: true,
						} );
					}, successMessageDelay );

					localStorage.setItem( 'st-import-end', +new Date() );
					return false;
				}
			} )
			.catch( ( error ) => {
				report(
					__( 'Final finishings Failed.', 'astra-sites' ),
					'',
					error
				);
				return false;
			} );

		return status;
	};

	const preventRefresh = ( event ) => {
		if ( importPercent < 100 ) {
			event.returnValue = __(
				'Are you sure you want to cancel the site import process?',
				'astra-sites'
			);
			return event;
		}
	};

	useEffect( () => {
		window.addEventListener( 'beforeunload', preventRefresh ); // eslint-disable-line
		return () => {
			window.removeEventListener( 'beforeunload', preventRefresh ); // eslint-disable-line
		};
	}, [ importPercent ] ); // Add importPercent as a dependency.

	// Add a useEffect to remove the event listener when importPercent is 100%.
	useEffect( () => {
		if ( importPercent === 100 ) {
			window.removeEventListener( 'beforeunload', preventRefresh );
		}
	}, [ importPercent ] );

	/**
	 * When try again button is clicked:
	 * There is a possibility that few/all the required plugins list is already installed.
	 * We cre-check the status of the required plugins here.
	 */
	useEffect( () => {
		if ( tryAgainCount > 0 ) {
			checkRequiredPlugins( storedState );
		}
	}, [ tryAgainCount ] );

	/**
	 * Start the pre import process.
	 * 		1. Install Astra Theme
	 * 		2. Install Required Plugins.
	 */
	useEffect( () => {
		/**
		 * Do not process when Import is already going on.
		 */
		if ( importStart || importEnd ) {
			return;
		}
		if ( ! importError ) {
			localStorage.setItem( 'st-import-start', +new Date() );
			percentage += 5;

			dispatch( {
				type: 'set',
				importStart: true,
				importPercent: percentage,
				importStatus: __( 'Starting Import.', 'astra-sites' ),
			} );
		}

		if ( themeActivateFlag && false === themeStatus ) {
			installAstra( storedState );
		} else {
			dispatch( {
				type: 'set',
				themeStatus: true,
			} );
		}

		// Handle async plugin installation with queue system
		installRequiredPlugins().catch( ( error ) => {
			console.error(
				'[useEffect] Error in installRequiredPlugins queue:',
				error
			);
			report(
				__(
					'Error occurred during plugin installation process.',
					'astra-sites'
				),
				'',
				error,
				true
			);
		} );
	}, [ templateResponse ] );

	/**
	 * Start the process only when:
	 * 		1. Required plugins are installed and activated.
	 * 		2. Astra Theme is installed
	 */
	useEffect( () => {
		if ( requiredPluginsDone && themeStatus ) {
			importPart1();
		}
	}, [ requiredPluginsDone, themeStatus ] );

	/**
	 * Start Part 2 of the import once the XML is imported sucessfully.
	 */
	useEffect( () => {
		if ( xmlImportDone ) {
			importPart2();
		}
	}, [ xmlImportDone ] );

	// State for deferred plugins (WooCommerce dependency handling)
	const [ deferredPlugins, setDeferredPlugins ] = React.useState( [] );
	const [ retryingDeferred, setRetryingDeferred ] = React.useState( false );

	/**
	 * Retry deferred plugins after WooCommerce is activated
	 */
	const retryDeferredPlugins = () => {
		if ( deferredPlugins.length === 0 || retryingDeferred ) {
			return;
		}

		setRetryingDeferred( true );

		// Move deferred plugins back to activation queue
		const pluginsToRetry = [ ...deferredPlugins ];
		setDeferredPlugins( [] );

		// Add them back to notActivatedList for retry
		dispatch( {
			type: 'set',
			notActivatedList: [ ...notActivatedList, ...pluginsToRetry ],
		} );

		setRetryingDeferred( false );
	};

	// This checks if all the required plugins are installed and activated.
	useEffect( () => {
		if ( notActivatedList.length <= 0 && notInstalledList.length <= 0 ) {
			// Check if we have deferred plugins to retry
			if ( deferredPlugins.length > 0 && ! retryingDeferred ) {
				retryDeferredPlugins();
				return;
			}

			// All plugins are truly done
			dispatch( {
				type: 'set',
				requiredPluginsDone: true,
			} );
		}
	}, [
		notActivatedList.length,
		notInstalledList.length,
		deferredPlugins.length,
	] );

	// Activate plugins one by one using the prioritized list
	useEffect( () => {
		// Installed all required plugins.
		if ( notActivatedList.length > 0 ) {
			activatePlugin( notActivatedList[ 0 ] );
		}
	}, [ notActivatedList.length ] );

	return (
		<DefaultStep
			content={
				<div className="middle-content middle-content-import">
					<>
						{ importPercent === 100 ? (
							<h1 className="import-done-congrats">
								{ __( 'Congratulations', 'astra-sites' ) }
								<span>{ ICONS.tada }</span>
							</h1>
						) : (
							<h1>
								{ __(
									'We are building your website…',
									'astra-sites'
								) }
							</h1>
						) }
						{ importError && (
							<div className="ist-import-process-step-wrap">
								<ErrorScreen />
							</div>
						) }
						{ ! importError && (
							<>
								<div className="ist-import-process-step-wrap">
									<ImportLoader />
								</div>
								{ importPercent !== 100 && (
									<Lottie
										loop
										animationData={ lottieJson }
										play
										style={ {
											height: 400,
											margin: '-70px auto -90px auto',
										} }
									/>
								) }
							</>
						) }
					</>
				</div>
			}
			actions={
				<>
					<PreviousStepLink before disabled customizeStep={ true }>
						{ __( 'Back', 'astra-sites' ) }
					</PreviousStepLink>
				</>
			}
		/>
	);
};

export default ImportSite;
