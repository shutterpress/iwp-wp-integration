/**
 * Admin JavaScript for FluentCart InstaWP Provisioning
 *
 * @package FCIP
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var FCIPAdmin = {
        
        // Form state tracking
        originalFormData: null,
        hasUnsavedChanges: false,
        
        /**
         * Initialize the admin functionality
         */
        init: function() {
            console.log('FCIPAdmin.init() called');
            this.bindEvents();
            this.initComponents();
            this.loadActiveTab();
            this.initKeyboardNavigation();
            this.initFormChangeTracking();
            this.initTabPreloading();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;
            
            // Tab navigation
            $('.fcip-admin-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).attr('href').substring(1);
                self.switchTab(tabId);
            });
            
            // Settings form submission
            $('#fcip-settings-form').on('submit', this.handleSettingsSubmit);
            
            // Reset settings confirmation
            $('.fcip-reset-settings').on('click', this.handleResetSettings);
            
            // Toggle dependent fields
            $('input[name="fcip_options[debug_mode]"]').on('change', this.toggleDebugFields);
            
            // AJAX actions
            $('.fcip-ajax-action').on('click', this.handleAjaxAction);
            
            // Templates refresh
            $('#fcip-refresh-templates').on('click', this.handleRefreshTemplates);
            
            // Plans refresh
            $('#fcip-refresh-plans').on('click', this.handleRefreshPlans);
            
            // Cache management
            $('#fcip-clear-transients').on('click', this.handleClearTransients);
            $('#fcip-warm-cache').on('click', this.handleWarmCache);
            
            // Site status refresh
            $('#refresh-site-status').on('click', this.handleRefreshSiteStatus.bind(this));
            
            // Form validation
            $('#fcip-settings-form input, #fcip-settings-form select').on('change', this.validateField);
            
            // Window beforeunload handler for unsaved changes
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
            
            // Browser back/forward navigation
            $(window).on('hashchange', function() {
                var hash = window.location.hash.substring(1);
                if (hash && $('#' + hash).length) {
                    self.switchTab(hash, false);
                }
            });
        },

        /**
         * Initialize tab-specific events
         */
        initTabEvents: function() {
            var self = this;
            
            // Handle settings form submission with tab preservation
            $(document).on('submit', '#fcip-settings-form', function(e) {
                // Store the current tab before form submission
                var currentTab = $('.fcip-admin-tabs .nav-tab-active').attr('href');
                if (currentTab) {
                    localStorage.setItem('fcip_active_tab_form_submit', currentTab.replace('#', ''));
                }
            });
            
            // Restore tab after page reload from form submission
            if (localStorage.getItem('fcip_active_tab_form_submit')) {
                var savedTab = localStorage.getItem('fcip_active_tab_form_submit');
                localStorage.removeItem('fcip_active_tab_form_submit');
                if ($('#' + savedTab).length) {
                    setTimeout(function() {
                        self.switchTab(savedTab);
                    }, 100);
                }
            }
            
            // Handle tab-specific button clicks with improved handling
            $(document).on('click', '#fcip-refresh-templates', function() {
                self.refreshSnapshots(true);
            });
            
            $(document).on('click', '#fcip-refresh-plans', function() {
                self.refreshPlans(true);
            });
            
            // Add loading states to data refresh buttons  
            $(document).on('click', '#fcip-refresh-snapshots, #fcip-refresh-plans', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.prop('disabled', true)
                   .text(fcip_admin.strings.loading || 'Loading...')
                   .addClass('updating-message');
            });
            
            // Enhance notices for better UX
            $(document).on('fcip:tab:changed', function(e, tabId) {
                // Clear any temporary notices when switching tabs
                $('.fcip-notice.temporary').fadeOut();
                
                // Show tab-specific help text
                self.showTabHelp(tabId);
            });
        },

        /**
         * Show tab-specific help text
         */
        showTabHelp: function(tabId) {
            // Remove any existing help notices
            $('.fcip-tab-help').remove();
            
            var helpText = '';
            switch(tabId) {
                case 'general-settings':
                    helpText = fcip_admin.strings.help_general || 'Configure your InstaWP API key and integration settings.';
                    break;
                case 'instawp-data':  
                    helpText = fcip_admin.strings.help_data || 'View and refresh cached data from InstaWP API. Click refresh buttons to update snapshots and plans.';
                    break;
                case 'testing':
                    helpText = fcip_admin.strings.help_testing || 'Test order creation and site functionality. Use these tools to verify your integration is working correctly.';
                    break;
                case 'documentation':
                    helpText = fcip_admin.strings.help_docs || 'Find shortcode examples, API references, and helpful links for using the plugin.';
                    break;
            }
            
            if (helpText) {
                var $help = $('<div class="fcip-tab-help" style="background: #f0f6fc; border-left: 4px solid #0073aa; padding: 10px 15px; margin-bottom: 20px; border-radius: 0 3px 3px 0;">' +
                    '<p style="margin: 0; color: #0073aa; font-size: 14px;"><strong>💡 Tip:</strong> ' + helpText + '</p>' +
                    '</div>');
                
                $('#' + tabId).prepend($help);
                
                // Auto-hide help after 10 seconds
                setTimeout(function() {
                    $help.fadeOut();
                }, 10000);
            }
        },

        /**
         * Initialize components
         */
        initComponents: function() {
            // Initialize tooltips
            this.initTooltips();
            
            // Set initial field states
            this.toggleDebugFields();
            
            // Initialize test order form
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            $('.fcip-tooltip').each(function() {
                var $tooltip = $(this);
                var title = $tooltip.attr('title');
                
                if (title) {
                    $tooltip.removeAttr('title');
                    $tooltip.on('mouseenter', function() {
                        $('<div class="fcip-tooltip-content">' + title + '</div>')
                            .appendTo('body')
                            .fadeIn(200);
                    }).on('mouseleave', function() {
                        $('.fcip-tooltip-content').fadeOut(200, function() {
                            $(this).remove();
                        });
                    });
                }
            });
        },

        /**
         * Initialize keyboard navigation for tabs
         */
        initKeyboardNavigation: function() {
            var self = this;
            $('.fcip-admin-tabs .nav-tab').on('keydown', function(e) {
                var $tabs = $('.fcip-admin-tabs .nav-tab');
                var currentIndex = $tabs.index(this);
                var targetIndex = -1;
                
                switch(e.keyCode) {
                    case 37: // Left arrow
                        e.preventDefault();
                        targetIndex = currentIndex > 0 ? currentIndex - 1 : $tabs.length - 1;
                        break;
                    case 39: // Right arrow
                        e.preventDefault();
                        targetIndex = currentIndex < $tabs.length - 1 ? currentIndex + 1 : 0;
                        break;
                    case 36: // Home
                        e.preventDefault();
                        targetIndex = 0;
                        break;
                    case 35: // End
                        e.preventDefault();
                        targetIndex = $tabs.length - 1;
                        break;
                }
                
                if (targetIndex !== -1) {
                    var $targetTab = $tabs.eq(targetIndex);
                    $targetTab.focus();
                    var tabId = $targetTab.attr('href').substring(1);
                    self.switchTab(tabId);
                }
            });
        },

        /**
         * Switch to a specific tab
         */
        switchTab: function(tabId, updateHash) {
            updateHash = updateHash !== false; // Default to true
            
            var $tabs = $('.fcip-admin-tabs');
            var $targetTab = $tabs.find('.nav-tab[href="#' + tabId + '"]');
            var $targetContent = $('#' + tabId);
            
            if ($targetTab.length && $targetContent.length) {
                // Update active tab
                $tabs.find('.nav-tab').removeClass('nav-tab-active');
                $targetTab.addClass('nav-tab-active');
                
                // Update active content with animation
                $('.fcip-tab-content').removeClass('active').hide();
                $targetContent.addClass('active').show();
                
                // Update URL hash (without triggering hashchange)
                if (updateHash) {
                    if (history.replaceState) {
                        history.replaceState(null, null, '#' + tabId);
                    } else {
                        window.location.hash = tabId;
                    }
                }
                
                // Save active tab to localStorage
                localStorage.setItem('fcip_active_tab', tabId);
                
                // Load tab-specific content if needed
                this.loadTabContent(tabId);
                
                // Show contextual help
                this.showTabHelp(tabId);
                
                // Preload next tab content
                this.preloadNextTab(tabId);
            }
        },

        /**
         * Load active tab from URL hash or localStorage
         */
        loadActiveTab: function() {
            var hash = window.location.hash.replace('#', '');
            var savedTab = localStorage.getItem('fcip_active_tab');
            var defaultTab = 'general-settings';
            
            // Priority: URL hash > localStorage > default
            var activeTab = hash || savedTab || defaultTab;
            
            // Ensure the tab exists
            if (!$('#' + activeTab).length) {
                activeTab = defaultTab;
            }
            
            // Force show the first tab immediately to prevent blank content
            if (!$('.fcip-tab-content.active').length) {
                $('#' + activeTab).addClass('active').show();
                $('.fcip-admin-tabs .nav-tab[href="#' + activeTab + '"]').addClass('nav-tab-active');
            }
            
            this.switchTab(activeTab, !hash); // Don't update hash if it's already set
        },

        /**
         * Load tab-specific content (for lazy loading)
         */
        loadTabContent: function(tabId) {
            // Load heavy content only when tab is activated
            switch(tabId) {
                case 'instawp-data':
                    // Refresh snapshots/plans if not loaded
                    if (!$('#fcip-snapshots-list').hasClass('loaded')) {
                        this.refreshSnapshots(false);
                    }
                    if (!$('#fcip-plans-list').hasClass('loaded')) {
                        this.refreshPlans(false);
                    }
                    break;
                    
                case 'testing':
                    // Initialize test components
                    break;
            }
        },
        
        /**
         * Show contextual help for tabs
         */
        showTabHelp: function(tabId) {
            var helpText = '';
            switch(tabId) {
                case 'general-settings':
                    helpText = 'Configure your basic plugin settings here.';
                    break;
                case 'instawp-data':
                    helpText = 'View your InstaWP snapshots and plans data.';
                    break;
                case 'testing':
                    helpText = 'Complete a FluentCart test purchase to verify your configuration.';
                    break;
                case 'documentation':
                    helpText = 'Find helpful resources and usage examples.';
                    break;
            }
            
            // Show help via console for now (could be enhanced with UI)
            if (helpText) {
                console.log('Tab Help - ' + tabId + ': ' + helpText);
            }
        },
        
        /**
         * Initialize form change tracking
         */
        initFormChangeTracking: function() {
            var self = this;
            var $form = $('form');
            if ($form.length === 0) return;
            
            // Store original form data
            this.originalFormData = $form.serialize();
            this.hasUnsavedChanges = false;
            
            // Track form changes
            $form.on('change input', 'input, select, textarea', function() {
                var currentData = $form.serialize();
                var hasChanges = currentData !== self.originalFormData;
                
                self.updateChangeIndicators(hasChanges);
            });
            
            // Form submission handling to preserve active tab
            $form.on('submit', function() {
                var activeTab = $('.nav-tab-active').attr('href');
                if (activeTab) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'fcip_active_tab',
                        value: activeTab.substring(1)
                    }).appendTo(this);
                }
                
                // Clear change tracking on form submit
                self.clearChangeTracking();
            });
        },
        
        /**
         * Update visual indicators for unsaved changes
         */
        updateChangeIndicators: function(hasChanges) {
            this.hasUnsavedChanges = hasChanges;
            var $activeTab = $('.nav-tab-active');
            var $form = $('form');
            
            if (hasChanges) {
                $activeTab.addClass('has-changes');
                $form.addClass('fcip-form-changed');
            } else {
                $('.nav-tab').removeClass('has-changes');
                $form.removeClass('fcip-form-changed');
            }
        },
        
        /**
         * Clear change tracking
         */
        clearChangeTracking: function() {
            this.hasUnsavedChanges = false;
            $('.nav-tab').removeClass('has-changes');
            $('form').removeClass('fcip-form-changed');
            this.originalFormData = $('form').serialize();
        },
        
        /**
         * Handle browser beforeunload for unsaved changes
         */
        handleBeforeUnload: function(e) {
            if (this.hasUnsavedChanges) {
                var message = 'You have unsaved changes. Are you sure you want to leave?';
                e.returnValue = message;
                return message;
            }
        },
        
        /**
         * Preload content for next tab
         */
        preloadNextTab: function(currentTabId) {
            var tabs = ['general-settings', 'instawp-data', 'testing', 'documentation'];
            var currentIndex = tabs.indexOf(currentTabId);
            var nextIndex = (currentIndex + 1) % tabs.length;
            var nextTabId = tabs[nextIndex];
            
            var $nextTab = $('#' + nextTabId);
            if ($nextTab.length && !$nextTab.data('preloaded')) {
                // Mark as preloaded to avoid duplicate requests
                $nextTab.data('preloaded', true);
                
                // Add subtle preloader indicator
                var $preloader = $('<div class="fcip-tab-preloader">Preparing content...</div>');
                $nextTab.append($preloader);
                
                // Simulate content loading (in real implementation, this would be AJAX)
                setTimeout(function() {
                    $preloader.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 1000);
            }
        },
        
        /**
         * Initialize tab preloading system
         */
        initTabPreloading: function() {
            var self = this;
            // Preload adjacent tabs on hover
            $('.fcip-admin-tabs .nav-tab').on('mouseenter', function() {
                var tabId = $(this).attr('href').substring(1);
                self.preloadNextTab(tabId);
            });
        },
        
        /**
         * Show save notification
         */
        showSaveNotification: function(message, isError) {
            isError = isError || false;
            
            // Remove existing notification
            $('.fcip-save-notification').remove();
            
            // Create notification element
            var $notification = $('<div class="fcip-save-notification' + (isError ? ' error' : '') + '">' + message + '</div>');
            
            // Add to page
            $('body').append($notification);
            
            // Show with animation
            setTimeout(function() {
                $notification.addClass('show');
            }, 100);
            
            // Hide after delay
            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        },

        /**
         * Refresh snapshots data
         */
        refreshSnapshots: function(showNotice) {
            showNotice = showNotice !== false;
            var $container = $('#fcip-snapshots-list');
            
            if ($container.length) {
                $container.html('<p>Loading snapshots...</p>');
                
                $.ajax({
                    url: fcip_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fcip_refresh_templates',
                        nonce: fcip_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.addClass('loaded');
                            if (showNotice) {
                                FCIPAdmin.showSaveNotification('Snapshots refreshed successfully');
                            }
                            // Reload the page to show updated data
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $container.html('<p class="error">Failed to load snapshots</p>');
                            if (showNotice) {
                                FCIPAdmin.showSaveNotification('Failed to refresh snapshots', true);
                            }
                        }
                    },
                    error: function() {
                        $container.html('<p class="error">Network error loading snapshots</p>');
                        if (showNotice) {
                            FCIPAdmin.showSaveNotification('Network error refreshing snapshots', true);
                        }
                    }
                });
            }
        },

        /**
         * Refresh plans data
         */
        refreshPlans: function(showNotice) {
            showNotice = showNotice !== false;
            var $container = $('#fcip-plans-list');
            
            if ($container.length) {
                $container.html('<p>Loading plans...</p>');
                
                $.ajax({
                    url: fcip_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fcip_refresh_plans',
                        nonce: fcip_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.addClass('loaded');
                            if (showNotice) {
                                FCIPAdmin.showSaveNotification('Plans refreshed successfully');
                            }
                            // Reload the page to show updated data
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $container.html('<p class="error">Failed to load plans</p>');
                            if (showNotice) {
                                FCIPAdmin.showSaveNotification('Failed to refresh plans', true);
                            }
                        }
                    },
                    error: function() {
                        $container.html('<p class="error">Network error loading plans</p>');
                        if (showNotice) {
                            FCIPAdmin.showSaveNotification('Network error refreshing plans', true);
                        }
                    }
                });
            }
        },

        /**
         * Handle settings form submission
         */
        handleSettingsSubmit: function(e) {
            var $form = $(this);
            var $submitButton = $form.find('input[type="submit"]');
            
            // Show loading state
            $submitButton.prop('disabled', true);
            $submitButton.val(fcip_admin.strings.saving || 'Saving...');
            
            // Add loading spinner
            $('<span class="fcip-loading"></span>').insertAfter($submitButton);
        },

        /**
         * Handle reset settings
         */
        handleResetSettings: function(e) {
            if (!confirm(fcip_admin.strings.confirm_reset)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            var $button = $(this);
            $button.prop('disabled', true);
            $('<span class="fcip-loading"></span>').insertAfter($button);
        },

        /**
         * Toggle debug fields
         */
        toggleDebugFields: function() {
            var $debugMode = $('input[name="fcip_options[debug_mode]"]');
            var $debugFields = $('.fcip-debug-field');
            
            if ($debugMode.is(':checked')) {
                $debugFields.show();
            } else {
                $debugFields.hide();
            }
        },

        /**
         * Handle AJAX actions
         */
        handleAjaxAction: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var action = $button.data('action');
            
            if (!action) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true);
            var originalText = $button.text();
            $button.text(fcip_admin.strings.loading || 'Loading...');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_' + action,
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCIP_Admin.showNotice(response.data.message, 'success');
                    } else {
                        FCIP_Admin.showNotice(response.data.message || fcip_admin.strings.error_occurred, 'error');
                    }
                },
                error: function() {
                    FCIP_Admin.showNotice(fcip_admin.strings.error_occurred, 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Validate field
         */
        validateField: function() {
            var $field = $(this);
            var fieldName = $field.attr('name');
            var fieldValue = $field.val();
            
            // Remove existing validation messages
            $field.siblings('.fcip-validation-message').remove();
            
            // Basic validation
            if ($field.prop('required') && !fieldValue) {
                FCIP_Admin.showFieldError($field, 'This field is required.');
                return false;
            }
            
            // Email validation
            if ($field.attr('type') === 'email' && fieldValue) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(fieldValue)) {
                    FCIP_Admin.showFieldError($field, 'Please enter a valid email address.');
                    return false;
                }
            }
            
            // URL validation
            if ($field.attr('type') === 'url' && fieldValue) {
                try {
                    new URL(fieldValue);
                } catch (e) {
                    FCIP_Admin.showFieldError($field, 'Please enter a valid URL.');
                    return false;
                }
            }
            
            return true;
        },

        /**
         * Show field error
         */
        showFieldError: function($field, message) {
            $field.addClass('fcip-field-error');
            $('<div class="fcip-validation-message error">' + message + '</div>').insertAfter($field);
        },

        /**
         * Show notice (enhanced for tabs)
         */
        showNotice: function(message, type, temporary) {
            type = type || 'info';
            temporary = temporary !== false; // Default to temporary
            
            var noticeClass = 'notice notice-' + type + ' is-dismissible fcip-notice';
            if (temporary) {
                noticeClass += ' temporary';
            }
            
            var $notice = $('<div class="' + noticeClass + '">' +
                '<p>' + message + '</p>' +
                '<button type="button" class="notice-dismiss"></button>' +
                '</div>');
            
            // Position notice appropriately  
            if ($('.fcip-admin-tabs').length) {
                $('.fcip-admin-tabs').after($notice);
            } else {
                $('.wrap h1').after($notice);
            }
            
            // Make notice dismissible
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut();
            });
            
            // Auto-dismiss temporary notices after 5 seconds
            if (temporary) {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Scroll to notice if not visible
            var noticeTop = $notice.offset().top;
            var scrollTop = $(window).scrollTop();
            var windowHeight = $(window).height();
            
            if (noticeTop < scrollTop || noticeTop > scrollTop + windowHeight) {
                $('html, body').animate({
                    scrollTop: noticeTop - 100
                }, 500);
            }
        },

        /**
         * Utility: Get form data as object
         */
        getFormData: function($form) {
            var formData = {};
            $form.find('input, select, textarea').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value = $field.val();
                
                if ($field.is(':checkbox')) {
                    value = $field.is(':checked') ? 'yes' : 'no';
                }
                
                if (name) {
                    formData[name] = value;
                }
            });
            
            return formData;
        },

        /**
         * Utility: Show loading overlay
         */
        showLoading: function() {
            if ($('.fcip-loading-overlay').length === 0) {
                $('<div class="fcip-loading-overlay"><div class="fcip-loading"></div></div>')
                    .appendTo('body');
            }
        },

        /**
         * Utility: Hide loading overlay
         */
        hideLoading: function() {
            $('.fcip-loading-overlay').remove();
        },

        /**
         * Handle refresh templates
         */
        handleRefreshTemplates: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_refresh_templates',
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCIP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated templates
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        FCIP_Admin.showNotice(response.data.message || 'An error occurred while refreshing templates.', 'error');
                    }
                },
                error: function() {
                    FCIP_Admin.showNotice('An error occurred while refreshing templates.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle refresh plans
         */
        handleRefreshPlans: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_refresh_plans',
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCIP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated plans
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        FCIP_Admin.showNotice(response.data.message || 'An error occurred while refreshing plans.', 'error');
                    }
                },
                error: function() {
                    FCIP_Admin.showNotice('An error occurred while refreshing plans.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle clear transients
         */
        handleClearTransients: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Confirm action
            if (!confirm('Are you sure you want to clear all cached data? This will force fresh API calls on the next page load.')) {
                return;
            }
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Clearing...');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_clear_transients',
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCIP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        FCIP_Admin.showNotice(response.data.message || 'An error occurred while clearing transients.', 'error');
                    }
                },
                error: function() {
                    FCIP_Admin.showNotice('An error occurred while clearing transients.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle cache warmup
         */
        handleWarmCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Warming up...');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_warm_cache',
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        FCIP_Admin.showNotice(response.data.message, 'success');
                        // Reload the page to show updated cache status
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        FCIP_Admin.showNotice(response.data.message || 'An error occurred while warming up cache.', 'error');
                    }
                },
                error: function() {
                    FCIP_Admin.showNotice('An error occurred while warming up cache.', 'error');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false);
                    $button.text(originalText);
                }
            });
        },

        /**
         * Handle site status refresh
         */
        handleRefreshSiteStatus: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true);
            $button.text('Refreshing...').addClass('loading');
            
            $.ajax({
                url: fcip_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'fcip_refresh_site_status',
                    nonce: fcip_admin.nonce
                },
                success: function(response) {
                    console.log('Refresh response:', response);
                    
                    if (response.success) {
                        // Show success notification
                        FCIPAdmin.showSaveNotification(response.data.message);
                        
                        // Reload the page to show updated status
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        console.error('Refresh failed:', response);
                        
                        // Show error notification
                        var errorMessage = response.data && response.data.message ? response.data.message : 'Failed to refresh site status.';
                        FCIPAdmin.showSaveNotification(errorMessage, true);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error:', textStatus, errorThrown);
                    
                    // Show error notification
                    FCIPAdmin.showSaveNotification('Network error occurred while refreshing site status.', true);
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false)
                           .text(originalText)
                           .removeClass('loading');
                }
            });
        },


    };

    // Initialize on document ready
    $(document).ready(function() {
        console.log('Admin.js loaded and document ready');
        console.log('Available variables:', typeof fcip_admin !== 'undefined' ? 'fcip_admin exists' : 'fcip_admin missing');
        FCIPAdmin.init();
    });

    // Make it globally accessible
    window.FCIPAdmin = FCIPAdmin;

})(jQuery);
