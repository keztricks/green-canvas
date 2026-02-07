import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Theme Management
(function() {
    'use strict';
    
    const THEME_KEY = 'theme-preference';
    
    function getStoredTheme() {
        return localStorage.getItem(THEME_KEY) || 'system';
    }
    
    function getEffectiveTheme() {
        const stored = getStoredTheme();
        if (stored === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        return stored;
    }
    
    function applyTheme(theme) {
        const effectiveTheme = theme === 'system' 
            ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
            : theme;
        
        if (effectiveTheme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        
        updateLogo(effectiveTheme);
    }
    
    function setTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
        applyTheme(theme);
        updateThemeUI(theme);
    }
    
    function updateLogo(effectiveTheme) {
        const logo = document.querySelector('.theme-logo');
        if (logo) {
            const baseUrl = 'https://cdn.brandfetch.io/idPY-7lInb/w/1780/h/440/theme/';
            logo.src = baseUrl + (effectiveTheme === 'dark' ? 'light' : 'dark') + '/logo.png';
        }
    }
    
    function updateThemeUI(currentTheme) {
        document.querySelectorAll('[data-theme-option]').forEach(option => {
            const theme = option.dataset.themeOption;
            const icon = option.querySelector('.theme-check');
            if (theme === currentTheme) {
                if (icon) icon.classList.remove('hidden');
                option.classList.add('bg-gray-100', 'dark:bg-gray-700');
            } else {
                if (icon) icon.classList.add('hidden');
                option.classList.remove('bg-gray-100', 'dark:bg-gray-700');
            }
        });
    }
    
    function initTheme() {
        const stored = getStoredTheme();
        applyTheme(stored);
        updateThemeUI(stored);
    }
    
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getStoredTheme() === 'system') {
            applyTheme('system');
        }
    });
    
    window.themeManager = {
        setTheme,
        getStoredTheme,
        getEffectiveTheme
    };
    
    if (typeof window.darkModeEnabled !== 'undefined' && window.darkModeEnabled) {
        initTheme();
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof window.darkModeEnabled !== 'undefined' && window.darkModeEnabled) {
            document.querySelectorAll('[data-theme-option]').forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    setTheme(option.dataset.themeOption);
                });
            });
        }
    });
})();
