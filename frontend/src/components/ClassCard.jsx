import React from 'react';
import { motion } from 'framer-motion';
import { AcademicCapIcon, UserGroupIcon } from '@heroicons/react/24/outline';

const ClassCard = ({ classData, onClick }) => {
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            whileHover={{ y: -4 }}
            onClick={onClick}
            className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden cursor-pointer transition-all hover:shadow-lg"
        >
            <div className="p-6">
                <div className="flex justify-between items-start mb-4">
                    <div className="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                        <AcademicCapIcon className="h-6 w-6 text-indigo-600" />
                    </div>
                    <span className="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-bold rounded uppercase">
                        {classData.academic_year}
                    </span>
                </div>
                <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-1">
                    {classData.name}
                </h3>
                <p className="text-gray-500 text-sm mb-4">
                    Section: {classData.section || 'N/A'}
                </p>
                <div className="flex items-center pt-4 border-t border-gray-50 dark:border-gray-700">
                    <div className="flex items-center text-gray-500 text-xs font-medium">
                        <UserGroupIcon className="h-4 w-4 mr-1" />
                        {classData.student_count || 0} Students
                    </div>
                </div>
            </div>
        </motion.div>
    );
};

export default ClassCard;
