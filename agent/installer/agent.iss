; =============================================================================
; EcomVision Sync Agent - Inno Setup installer
; =============================================================================
; Builds the setup .exe a shop runs to connect its billing software to its
; online store (docs/SYNC.md). Compile it with agent\build-installer.ps1, after
; agent/build.sh and `php artisan sync:agent-sign`.
;
; It asks ONE thing, as Restrovision's hub does: the setup code from the store's
; panel. Everything else comes from the online store when the code is checked:
; whose store it is, and where the billing software's API is (its address and
; key, entered in the panel under Shop software sync -> Billing software
; connection). The check also makes sure the billing software answers there, so
; Next only moves on when the agent will work. Then the agent installs itself as
; a Windows service that syncs every 5 minutes and updates itself. The check and
; the install are the agent's own commands (check-code, install), so the console
; setup behaves the same.
;
; Built four times (Arch=x64, x86, x64-win7, x86-win7; see build-installer.ps1):
; EcomVisionSyncAgent-Setup-x64.exe and -x86.exe for Windows 10 and 11, -x64-win7.exe
; and -x86-win7.exe for Windows 7 and 8.1. The panel offers all four, the one the
; browser's computer needs marked Recommended. 32-bit runs on 64-bit Windows too. Each identical file serves every shop: nothing about a shop is inside.
;
; UNSIGNED (Authenticode), as Restrovision's hub: Windows SmartScreen warns
; ("Windows protected your PC" -> More info -> Run anyway) until a code-signing
; certificate is added. The agent's own updates are RSA-signed regardless.
; =============================================================================

#define AppName      "EcomVision Sync Agent"
#define AppPublisher "EcomVision"
#ifndef AppVersion
  #define AppVersion "1.0.0"
#endif
#ifndef Arch
  #define Arch "x64"
#endif
; x64 and x86 need Windows 10 or later (the agent is built with a current Go); x64-win7 and
; x86-win7 carry the builds made with Go 1.20, the last Go that runs on Windows 7 and 8.1.
#if Arch == "x64"
  #define AgentBuild "amd64"
  #define WindowsLabel "64-bit Windows 10 / 11"
  #define MinWindows "10.0"
#elif Arch == "x86"
  #define AgentBuild "386"
  #define WindowsLabel "32-bit Windows 10 / 11"
  #define MinWindows "10.0"
#elif Arch == "x64-win7"
  #define AgentBuild "amd64-win7"
  #define WindowsLabel "64-bit Windows 7 / 8.1"
  #define MinWindows "6.1sp1"
#else
  #define AgentBuild "386-win7"
  #define WindowsLabel "32-bit Windows 7 / 8.1"
  #define MinWindows "6.1sp1"
#endif
#define Dist         "..\..\resources\agent\dist"

[Setup]
AppId={{6B1E5C2A-3F47-4D8E-9A61-7C0D2B4E8F13}
AppName={#AppName}
AppVersion={#AppVersion}
AppVerName={#AppName} {#AppVersion} ({#WindowsLabel})
AppPublisher={#AppPublisher}
VersionInfoVersion={#AppVersion}
; The program and its uninstaller. The service itself lives in its own locked
; folder (C:\ProgramData\EcomVisionAgent), which the agent sets up.
DefaultDirName={commonpf}\{#AppName}
DisableDirPage=yes
DisableProgramGroupPage=yes
OutputDir={#Dist}
OutputBaseFilename=EcomVisionSyncAgent-Setup-{#Arch}
Compression=lzma2/max
SolidCompression=yes
; It installs a Windows service, so it needs Administrator.
PrivilegesRequired=admin
; Older Windows gets Windows' own clear "requires Windows ..." message rather than an agent that cannot start.
MinVersion={#MinWindows}
#if Arch == "x64" || Arch == "x64-win7"
; Refuses 32-bit Windows with a clear message, rather than an agent that cannot start.
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
#endif
WizardStyle=modern
UninstallDisplayName={#AppName}
UninstallDisplayIcon={app}\ecomvision-agent.exe
CloseApplications=no

[Messages]
WelcomeLabel2=This connects your shop's billing software to your online store.%n%nYou need one thing: a setup code from your store's panel%n(Settings > Shop software sync > Create agent setup code).%n%nRun this on the PC that runs your billing software. The agent then works in the background and keeps products, stock, customers and web orders in sync every 5 minutes.
FinishedHeadingLabel=The sync agent is installed
FinishedLabelNoIcons=The agent starts with Windows, syncs every 5 minutes and updates itself.%n%nIn your store's panel, Settings > Shop software sync shows "Syncing" within a few minutes. You do not need to run this setup again.

[Files]
; The setup code check unpacks the same file to {tmp} first (ExtractTemporaryFile
; finds it by this DestName).
Source: "{#Dist}\ecomvision-agent-{#AgentBuild}.exe"; DestDir: "{app}"; DestName: "ecomvision-agent.exe"; Flags: ignoreversion

[UninstallRun]
; Stops and removes the service and its folder before the program goes.
Filename: "{app}\ecomvision-agent.exe"; Parameters: "uninstall --quiet"; Flags: runhidden waituntilterminated; RunOnceId: "RemoveAgentService"

[Code]
var
  CodePage: TInputQueryWizardPage;
  Connected: String;
  InstallError: String;

// Runs one of the agent's installer commands, hidden, from wherever the agent
// is. Message gets the line it wrote: what to show, or why it failed.
function RunAgent(const Exe, Params: String; var Message: String): Boolean;
var
  MessageFile: String;
  ResultCode: Integer;
  Text: AnsiString;
begin
  MessageFile := ExpandConstant('{tmp}\agent-message.txt');
  DeleteFile(MessageFile);
  Message := '';

  if not Exec(Exe, Params + ' --message-file "' + MessageFile + '"', '', SW_HIDE, ewWaitUntilTerminated, ResultCode) then
  begin
    Message := 'The agent could not start: ' + SysErrorMessage(ResultCode);
    Result := False;
    Exit;
  end;

  if LoadStringFromFile(MessageFile, Text) then
    Message := Trim(String(Text));
  Result := ResultCode = 0;
  if (not Result) and (Message = '') then
    Message := 'The agent stopped with error ' + IntToStr(ResultCode) + '.';
end;

// The agent, unpacked to {tmp} for the check before anything is installed.
function CheckingAgent: String;
begin
  ExtractTemporaryFile('ecomvision-agent.exe');
  Result := ExpandConstant('{tmp}\ecomvision-agent.exe');
end;

// A code pasted with spaces or line breaks around or inside it still works.
function Cleaned(const Value: String): String;
begin
  Result := Trim(Value);
  StringChangeEx(Result, #13, '', True);
  StringChangeEx(Result, #10, '', True);
  StringChangeEx(Result, ' ', '', True);
end;

procedure InitializeWizard;
begin
  CodePage := CreateInputQueryPage(wpWelcome,
    'Setup code',
    'Connect this PC to your online store',
    'In your store''s panel, open Settings > Shop software sync and choose ' +
    '"Create agent setup code". Copy the code it shows (it starts with EV1.) and paste it here.' + #13#10 + #13#10 +
    'When you press Next, setup checks the code and reaches your billing software, using the ' +
    'connection set in your store''s panel. The code works like a password; create a new one if you have lost it.');
  CodePage.Add('Setup code:', False);
end;

function NextButtonClick(CurPageID: Integer): Boolean;
var
  Message: String;
begin
  Result := True;
  if CurPageID <> CodePage.ID then
    Exit;

  CodePage.Values[0] := Cleaned(CodePage.Values[0]);
  if CodePage.Values[0] = '' then
  begin
    MsgBox('Paste the setup code from your store''s panel.', mbError, MB_OK);
    Result := False;
    Exit;
  end;

  WizardForm.NextButton.Enabled := False;
  WizardForm.CancelButton.Enabled := False;
  try
    Result := RunAgent(CheckingAgent, 'check-code --code "' + CodePage.Values[0] + '"', Message);
  finally
    WizardForm.NextButton.Enabled := True;
    WizardForm.CancelButton.Enabled := True;
  end;

  if Result then
    Connected := Message
  else
    MsgBox('Setup can''t continue yet:' + #13#10 + #13#10 + Message + #13#10 + #13#10 +
      'Fix that, then press Next again.', mbError, MB_OK);
end;

// The Ready page says what was found, in the shop's words.
function UpdateReadyMemo(Space, NewLine, MemoUserInfoInfo, MemoDirInfo, MemoTypeInfo, MemoComponentsInfo, MemoGroupInfo, MemoTasksInfo: String): String;
begin
  Result := 'Connected to:' + NewLine + Space + Connected + NewLine + NewLine +
    'The agent will be installed as a Windows service that starts with Windows and syncs every 5 minutes.';
end;

// After the files are in place: the agent sets up its service with the code.
procedure CurStepChanged(CurStep: TSetupStep);
var
  Message: String;
begin
  if CurStep <> ssPostInstall then
    Exit;

  WizardForm.StatusLabel.Caption := 'Starting the sync agent...';
  if not RunAgent(ExpandConstant('{app}\ecomvision-agent.exe'), 'install --code "' + CodePage.Values[0] + '"', Message) then
  begin
    InstallError := Message;
    MsgBox('The sync agent could not be started:' + #13#10 + #13#10 + Message + #13#10 + #13#10 +
      'Nothing was synced. Fix the problem and run this setup again.', mbError, MB_OK);
  end;
end;

procedure CurPageChanged(CurPageID: Integer);
begin
  if (CurPageID = wpFinished) and (InstallError <> '') then
  begin
    WizardForm.FinishedHeadingLabel.Caption := 'The sync agent is not running';
    WizardForm.FinishedLabel.Caption := 'Setup copied the agent but could not start it:' + #13#10 + #13#10 +
      InstallError + #13#10 + #13#10 + 'Fix the problem and run this setup again.';
  end;
end;
