/*
 * Name:    NeoSentry NMS 
 * Author:  Dan Elliott
 * Description:
 *    This program will run scripts as root, passing the arguments to that script file to handle.
 *    Hardcode the scripts and ensure they are read only.
 * 
 * Some Useful tutorials:
 *  https://www.infoq.com/articles/inotify-linux-file-system-event-monitoring
 *  http://www.enderunix.org/docs/eng/daemon.php
 *
 * This program will run scripts as root. Hardcode the scripts and ensure they are read only.
 *   
 * Set the owner of the executable to root and it will run as root.
 *  gcc -o neosentry neosentry.c
 *  chown root neosentry
 *  chmod u+s neosentry   [or: chmod 4755 program]
 * 
 * To Limit access a little more to only owner and group do this:
 *  chown root:www-data handler
 *  chmod 4550 handler
 * 
 *
*/

#include <errno.h>
#include <stdio.h>
#include <string.h>
#include <stdlib.h>
#include <sys/types.h>
#include <unistd.h>

int main(int argc, char *argv[]) {
    /* Get the real and effective UID's for reporting */
    uid_t ruid = getuid ();    uid_t euid = geteuid ();
    int cmdrdy = 0;
    
    /* Ensure we have the right number of arguments */
    if (argc < 2) {
        //printf("Usage: %s cmd [arguments]\n\nReal UID: %d Effective UID: %d\n", argv[0], ruid, euid);
        //return 1;
    }
    
    /* only allow certain arguments - I'm letting the subscript handle this so I don't really care about this right now*/
    if (strcmp(argv[1],"ping.php")==0) {
        cmdrdy=1;
    } else if (strcmp(argv[1],"sysconfig.py")==0 && argc > 1) {
        cmdrdy=1;
    }
    cmdrdy=1; // just pass everything to the script

    
    /* fork and execute */
    if (cmdrdy==1) {
        //++argv; /* remove the first argument */
        char *phpcmd = "/usr/bin/php";
        char *phpfile = "/usr/share/neosentry/neosentry.php";
        char *newargs[sizeof(*argv)+1];//(char**)realloc(newargs, sizeof(*argv)+1);
        newargs[0] = phpcmd;
        newargs[1] = phpfile;

        int i;
        for (i=1; i<sizeof(*argv)+1; i++) {
            newargs[i+1] = argv[i];
        }


        int pid = fork();
        if(pid == 0){ /* the forked process will do this */
            //printf("Child's PID is %d. Parent's PID is %d\n", getpid(), getppid());
            execv(newargs[0], newargs);
        } else { /* the main process will wait */
            wait(NULL);
            exit(0);
        }
        return 0;
    } else {
        /* no valid command was found */
        printf("No valid command was found for %s\n",argv[1]);
        return 1;
    }

}

int checkfromfile(char *arg, char *settingsFile) {
    /* Variables */
    /* char *scriptmapfile = "scripts/scriptmap.conf"; /* maps commands to script. root access only. */
    /* char *settingsfile = "settings.conf"; /* variable settings written by the front-end*/

    /* Read in the settings file to get the interpreter path and commands */
    FILE *fp;
    if (fp = fopen("configs/settings.conf","r")) {
        
    }
    fclose(fp);
    
}
