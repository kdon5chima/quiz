<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBT System - Unique Heights Junior and Senior High School</title>
    <!-- Load Tailwind CSS -->
    <script src="tailwindcdn.js"></script>
    <!-- Use Inter font -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f6f9; /* Light professional background */
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">

    <!-- Main Welcome Card Container -->
    <div class="bg-white shadow-2xl rounded-xl max-w-6xl w-full p-6 md:p-10 lg:p-12 border-t-4 border-indigo-600">
        
        <!-- Header: Logo and Title -->
        <header class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 pb-4 border-b">
            <!-- School Logo (Top Left) -->
            <div class="flex items-center space-x-3 mb-4 md:mb-0">
                <!-- Placeholder Logo: Replace with your actual school logo image -->
                <img src="log.jpg" 
                     onerror="this.src='https://placehold.co/64x64/4f46e5/ffffff?text=LOGO';"
                     alt="School Logo" 
                     class="w-16 h-16 rounded-full shadow-md">
                <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                    Unique Heights Junior and Senior High School<br /><font color="#4b0c0cff"> CBT System Platform</font>
                </h1>
            </div>
            
            <div class="text-sm text-gray-500 font-medium">
                CBT System Platform: <span id="currentDate"></span>
            </div>
        </header>

        <!-- Content Section: Instructions and Image -->
        <!-- Swapped the order of the two columns below -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            
            <!-- 1. School Picture and Start Button (NOW LEFT COLUMN) -->
            <div class="flex flex-col items-center justify-center space-y-6">
                
                <!-- School Picture -->
                <figure class="w-full max-w-md bg-gray-100 rounded-lg overflow-hidden shadow-xl transform transition duration-500 hover:scale-[1.02]">
                    <!-- Placeholder Image: Replace with your actual school building or campus image -->
                    <img src="logo.png" 
                         onerror="this.src='https://placehold.co/800x450/3730a3/ffffff?text=SCHOOL+CAMPUS';"
                         alt="Picture of the School Campus" 
                         class="w-full h-auto object-cover">
                    <figcaption class="p-3 text-center text-sm text-gray-500">
                        Our institution is committed to a seamless and fair examination process.
                    </figcaption>
                </figure>
                
                <!-- Click to Start Button -->
                <a href="cbt1/login.php"><button 
                    id="startButton"
                    class="w-full max-w-xs px-8 py-3 bg-indigo-600 text-white text-lg font-bold rounded-lg shadow-lg 
                           hover:bg-indigo-700 transition duration-300 ease-in-out 
                           transform hover:scale-105 active:scale-95 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                    Click to Login...
                </button></a>
            </div>

            <!-- 2. Instructions and Summary (NOW RIGHT COLUMN) -->
            <div class="space-y-6">
                <h2 class="text-2xl font-semibold text-indigo-700 border-l-4 border-indigo-500 pl-3">
                    Computer-Based Test Requirements
                </h2>
                
                <!-- Brief Summary -->
                <p class="text-gray-700 leading-relaxed">
                    Welcome to the CBT System platform. Before you begin your examination, please ensure you meet the following essential requirements. This CBT environment is designed for fair and accurate assessment.
                </p>

                <!-- Detailed Requirements List -->
                <ul class="list-none space-y-3 text-gray-600">
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium text-gray-800">Basic computer Interface Familiarity is required   </span> 
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium text-gray-800">Ensure Valid Credentials are used  </span> 
                    </li>
                    <li class="flex items-start">
                        <svg class="w-5 h-5 text-green-500 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium text-gray-800">Strict Adherence to Time Limits and Specific Exam Instructions is important  </span> 
                    </li>
                </ul>
            </div>
            
        </div>

        <!-- Footer -->
        <footer class="mt-8 pt-4 border-t text-center text-sm text-gray-500">
            &copy; 2025 Unique Heights Junior and Senior High School All rights reserved. CBT System v1.0
        </footer>
    </div>

    <script>
        // Set the current date in the header
        document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        // JavaScript for the "Click to Start" button functionality
        document.getElementById('startButton').addEventListener('click', function() {
            // Since we are generating a single HTML file, we simulate the link.
            window.location.href = 'login.html'; 
        });
    </script>

</body>
</html>
