<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Date & Time Picker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            text-align: center;
        }

        h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
        }

        /* Initial Icon State */
        .icon-container {
            margin-bottom: 30px;
            position: relative;
            display: inline-block;
        }

        #initialIcon {
            cursor: pointer;
            display: inline-block;
            padding: 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 3px dashed #667eea;
        }

        #initialIcon:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            border-color: #764ba2;
        }

        #initialIcon img {
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .icon-label {
            margin-top: 10px;
            color: #667eea;
            font-weight: 500;
            font-size: 14px;
        }

        /* Combined Field State */
        #combinedWrapper {
            position: relative;
            margin-top: 20px;
            /*opacity: 0;*/
            /*transform: translateY(-20px);*/
            /*transition: all 0.4s ease;*/
            display: none;
        }

        #combinedWrapper.show {
            /*opacity: 1;*/
            /*transform: translateY(0);*/
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        #insideIcon {
            position: absolute;
            left: 15px;
            cursor: pointer;
            z-index: 2;
            padding: 5px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        #insideIcon:hover {
            transform: scale(1.1);
        }

        #dateInput {
            width: 100%;
            padding: 16px 20px 16px 65px;
            font-size: 16px;
            border: 2px solid #667eea;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
            color: #333;
        }

        #dateInput:focus {
            outline: none;
            border-color: #764ba2;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        #dateInput::placeholder {
            color: #999;
        }

        .clear-btn {
            position: absolute;
            right: 15px;
            background: none;
            border: none;
            color: #ff6b6b;
            cursor: pointer;
            font-size: 24px;
            width: 30px;
            height: 30px;
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
            opacity: 0.7;
        }

        .clear-btn:hover {
            background: rgba(255, 107, 107, 0.1);
            opacity: 1;
            transform: scale(1.1);
        }

        /* Selected Date Display */
        .selected-date-info {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9ff 0%, #eef1ff 100%);
            border-radius: 15px;
            border-left: 5px solid #667eea;
            text-align: left;
            display: none;
        }

        .selected-date-info.show {
            display: block;
        }

        .date-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .date-value {
            color: #333;
            font-size: 18px;
            font-weight: 500;
        }

        .time-display {
            color: #764ba2;
            font-weight: 500;
            margin-top: 5px;
        }

        /* Flatpickr Calendar - Manual positioning */
        .flatpickr-calendar {
            border-radius: 15px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
            border: none !important;
            margin-top: 10px !important;
            z-index: 99999 !important;
        }

        /* Custom Done button */
        .custom-done-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 10px;
            transition: all 0.2s ease;
        }

        .custom-done-btn:hover {
            background: #764ba2;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
<div class="container">
    <h2>📅 Date & Time Picker</h2>

    <!-- Initial Icon State -->
    <div class="icon-container">
        <div id="initialIcon" title="Click to select date & time">
            <img src="https://cdn-icons-png.flaticon.com/512/747/747310.png" width="40" alt="Calendar Icon"/>
        </div>
        <div class="icon-label">Select Date & Time</div>
    </div>

    <!-- Combined Field State -->
    <div id="combinedWrapper">
        <div class="input-group">
            <img
                id="insideIcon"
                src="https://cdn-icons-png.flaticon.com/512/747/747310.png"
                width="24"
                alt="Calendar Icon"
            />
            <input
                type="text"
                id="dateInput"
                placeholder="Select date and time"
                readonly
            >
            <button class="clear-btn" id="clearBtn" title="Clear selection">×</button>
        </div>
    </div>

    <!-- Selected Date Display -->
    <div class="selected-date-info" id="dateInfo">
        <div class="date-title">Selected Date & Time</div>
        <div class="date-value" id="selectedDate">Not selected</div>
        <div class="time-display" id="selectedTime"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // datepicker.js - Complete fixed version

    // Initialize variables
    const initialIcon = document.getElementById('initialIcon');
    const combinedWrapper = document.getElementById('combinedWrapper');
    const dateInput = document.getElementById('dateInput');
    const clearBtn = document.getElementById('clearBtn');

    let isInitialState = true;
    let flatpickrInstance = null;

    // Initialize Flatpickr
    function initializeFlatpickr() {
        flatpickrInstance = flatpickr(dateInput, {
            enableTime: true,
            dateFormat: "d-m-Y H:i", // Format: 12-10-2025 14:30
            time_24hr: true,
            minuteIncrement: 5,
            onOpen: () => setTimeout(positionCalendar, 10),
            onChange: (selectedDates) => {
                if (selectedDates.length > 0) {
                    // Flatpickr will automatically format it as d-m-Y H:i
                    // No need for manual formatting
                }
            },
            onClose: (selectedDates) => {
                if (selectedDates.length > 0) {
                    initialIcon.style.display = "none";
                    combinedWrapper.style.display = "block";
                    setTimeout(() => combinedWrapper.classList.add('show'), 10);
                    clearBtn.style.display = "flex";
                    isInitialState = false;
                }
            }
        });
    }

    // Position calendar below element
    function positionCalendar() {
        const calendar = document.querySelector('.flatpickr-calendar');
        if (!calendar) return;
        const targetRect = isInitialState ? initialIcon.getBoundingClientRect() : dateInput.getBoundingClientRect();
        calendar.style.position = 'fixed';
        calendar.style.top = (targetRect.bottom + window.scrollY + 10) + 'px';
        calendar.style.left = (targetRect.left + window.scrollX) + 'px';
        calendar.style.zIndex = '99999';
    }

    // Reset to initial state
    function resetToInitialState() {
        initialIcon.style.display = "inline-block";
        combinedWrapper.classList.remove('show');
        setTimeout(() => combinedWrapper.style.display = "none", 400);
        clearBtn.style.display = "none";
        isInitialState = true;
        dateInput.value = "";
        flatpickrInstance && flatpickrInstance.clear();
    }

    // Setup event listeners
    function setupEventListeners() {
        initialIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            isInitialState && flatpickrInstance && dateInput.click();
        });

        const insideIcon = document.getElementById('insideIcon');
        insideIcon && insideIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            !isInitialState && flatpickrInstance && dateInput.click();
        });

        clearBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            resetToInitialState();
        });

        document.addEventListener('click', (e) => {
            const calendar = document.querySelector('.flatpickr-calendar');
            calendar && !calendar.contains(e.target) &&
            !e.target.closest('#initialIcon, #insideIcon, #dateInput') &&
            e.target !== clearBtn &&
            flatpickrInstance && flatpickrInstance.isOpen &&
            flatpickrInstance.close();
        });
    }

    // Initialize
    function init() {
        initializeFlatpickr();
        resetToInitialState();
        setupEventListeners();
    }

    // Start
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
</script>
</body>
</html>
