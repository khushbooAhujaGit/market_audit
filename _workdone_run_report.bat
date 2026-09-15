@echo off
setlocal enabledelayedexpansion

set "REPORT=_workdone_report"

for %%R in ("D:\marketaudit_tnbttech" "D:\hrms-payroll" "D:\ams_laravel" "D:\headcount_backup") do (
  cd /d %%R
  if exist .git (
    if not exist "%REPORT%" mkdir "%REPORT%"
    git log --all --since=2026-08-01 --date=iso-strict --pretty=format:"%%H|%%ad|%%an|%%ae|%%s" > "%REPORT%\gitlog.txt" 2>&1
    echo. >> "%REPORT%\gitlog.txt"
    git log --all --pretty=format:"%%an <%%ae>" > "%REPORT%\all_authors.txt" 2>&1
    echo. >> "%REPORT%\all_authors.txt"
    git status > "%REPORT%\status.txt" 2>&1
    git status --porcelain > "%REPORT%\status_porcelain.txt" 2>&1
    git diff --stat > "%REPORT%\diff_stat.txt" 2>&1
    git diff --cached --stat > "%REPORT%\diff_cached_stat.txt" 2>&1
    git branch -a > "%REPORT%\branches.txt" 2>&1
    git rev-parse --abbrev-ref HEAD >> "%REPORT%\branches.txt" 2>&1
    git log --all --since=2026-08-01 --name-status --pretty=format:"COMMIT|%%H|%%ad|%%an|%%s" > "%REPORT%\gitlog_files.txt" 2>&1
    echo. >> "%REPORT%\gitlog_files.txt"
    git log -1 --pretty=format:"%%H|%%ad|%%an|%%s" --date=iso-strict > "%REPORT%\last_commit.txt" 2>&1
  ) else (
    echo NOT_A_GIT_REPO > "%REPORT%\status.txt"
  )
)

echo DONE > "D:\marketaudit_tnbttech\_workdone_report\_complete.txt"
