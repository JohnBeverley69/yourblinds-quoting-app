# -*- coding: utf-8 -*-
from reportlab.lib.pagesizes import A4
from reportlab.lib.units import mm
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer, Table,
                                TableStyle, HRFlowable)

INK   = colors.HexColor("#1e1e1e")
MUTED = colors.HexColor("#5f5f5f")
NAVY  = colors.HexColor("#1f3a5f")
RED   = colors.HexColor("#a11f1f")
REDBG = colors.HexColor("#fbeaea")
RULE  = colors.HexColor("#cfcfcf")
BAND  = colors.HexColor("#eef2f6")
BOXBG = colors.HexColor("#f7f7f5")

styles = getSampleStyleSheet()
H1  = ParagraphStyle('H1', parent=styles['Title'], fontSize=16, leading=19, textColor=INK, alignment=TA_LEFT, spaceAfter=1)
SUB = ParagraphStyle('SUB', parent=styles['Normal'], fontSize=9, leading=12, textColor=MUTED, spaceAfter=3)
H2  = ParagraphStyle('H2', parent=styles['Heading2'], fontSize=10.5, leading=13, textColor=NAVY, spaceBefore=7, spaceAfter=3)
BODY= ParagraphStyle('BODY', parent=styles['Normal'], fontSize=9, leading=12, textColor=INK)
SMALL=ParagraphStyle('SMALL', parent=styles['Normal'], fontSize=8, leading=10.5, textColor=MUTED)
CALL= ParagraphStyle('CALL', parent=styles['Normal'], fontSize=9.5, leading=12.5, textColor=INK)
CELLH=ParagraphStyle('CELLH', parent=styles['Normal'], fontSize=9, leading=11.5, textColor=INK, fontName='Helvetica-Bold')
CELL =ParagraphStyle('CELL', parent=styles['Normal'], fontSize=9, leading=11.5, textColor=INK)

story = []
story.append(Paragraph("Renault Trafic &mdash; persistent electrical / infotainment faults", H1))
story.append(Paragraph("Diagnostic request for the service department. Please read the highlighted note and carry out the listed tests.", SUB))

info = [[Paragraph("<b>Registration:</b> YO24 PBF", BODY), Paragraph("<b>Owner:</b> ____________________", BODY), Paragraph("<b>Date:</b> ____________", BODY)],
        [Paragraph("<b>VIN:</b> ____________________", BODY), Paragraph("<b>Mileage:</b> ____________", BODY), Paragraph("<b>Renault UK case ref:</b> ____________", BODY)]]
t = Table(info, colWidths=[175, 200, 145])
t.setStyle(TableStyle([('TOPPADDING',(0,0),(-1,-1),2),('BOTTOMPADDING',(0,0),(-1,-1),2),('LEFTPADDING',(0,0),(-1,-1),0)]))
story.append(t)
story.append(HRFlowable(width="100%", thickness=1, color=RULE, spaceBefore=4, spaceAfter=4))

# Callout
callout = Table([[Paragraph("<b>IMPORTANT &mdash; please read first.</b>  A <b>new head unit</b> and a <b>new telematics module</b> have already been fitted, and the faults below remain. Two new modules behaving identically indicates the fault is <b>not in the modules</b>. Please stop replacing modules and diagnose <b>what feeds them</b>: the antennas, coax, connectors, earth/ground points and the 12V supply. Several faults appeared or worsened after that work &mdash; please confirm no antenna or battery-sensor connections were disturbed.", CALL)]],
                colWidths=[520])
callout.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,-1),REDBG),('BOX',(0,0),(-1,-1),1,RED),
                             ('LEFTPADDING',(0,0),(-1,-1),8),('RIGHTPADDING',(0,0),(-1,-1),8),
                             ('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),6)]))
story.append(callout)

# Symptoms
story.append(Paragraph("Reported symptoms (grouped by likely common cause)", H2))
sym = [[Paragraph("Group", CELLH), Paragraph("Symptom", CELLH)],
       [Paragraph("<b>A &mdash; Reception</b><br/><font size=7 color='#5f5f5f'>(antenna / coax / earth)</font>", CELL),
        Paragraph("&bull; GPS: permanent <i>'Searching for GPS signal'</i>.<br/>"
                  "&bull; DAB radio: loses signal on strong stations (Radio 2 / Radio 5) on main roads &amp; motorways (Derby, Doncaster, Kettering).<br/>"
                  "&bull; Traffic-sign recognition: intermittent and shows the <b>wrong speed limit</b>.", CELL)],
       [Paragraph("<b>B &mdash; 12V power</b><br/><font size=7 color='#5f5f5f'>(charging / drain / earth)</font>", CELL),
        Paragraph("&bull; Keyless: <i>'place key fob in zone'</i> &mdash; <b>will not start</b> (new fault; fob always carried in pocket, previously fine).<br/>"
                  "&bull; Low-battery warning previously appeared <b>roughly every 2 weeks</b>; has <b>stopped</b> since the dealer work &mdash; no warning now.", CELL)],
       [Paragraph("<b>C &mdash; Infotainment</b><br/><font size=7 color='#5f5f5f'>(possibly not sleeping)</font>", CELL),
        Paragraph("&bull; Radio <b>freezes</b> regularly &mdash; needs rebooting at least once a day.<br/>"
                  "&bull; After engine off then on: radio shows <b>ON, volume 20&ndash;22, but no sound</b> until rebooted.<br/>"
                  "&bull; Was previously 'fixed' then returned.", CELL)]]
ts = Table(sym, colWidths=[95, 425])
ts.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),NAVY),('TEXTCOLOR',(0,0),(-1,0),colors.white),
                        ('ROWBACKGROUNDS',(0,1),(-1,-1),[colors.white,BAND]),
                        ('GRID',(0,0),(-1,-1),0.5,RULE),('VALIGN',(0,0),(-1,-1),'TOP'),
                        ('TOPPADDING',(0,0),(-1,-1),4),('BOTTOMPADDING',(0,0),(-1,-1),4),
                        ('LEFTPADDING',(0,0),(-1,-1),6),('RIGHTPADDING',(0,0),(-1,-1),6)]))
story.append(ts)

# Requested tests
story.append(Paragraph("Diagnostics requested (please carry out and record the result of each)", H2))
tests = [
 "Physically verify <b>every antenna connector (GPS + DAB)</b> is correctly seated at the new modules and at the aerial &mdash; confirm none were left disconnected or pinched during the module replacements.",
 "<b>Antenna / coax continuity &amp; signal test</b> for GPS and DAB.",
 "Check and clean the <b>common earth / ground points</b> for the infotainment and telematics.",
 "<b>Parasitic draw (quiescent current) test</b> after the vehicle sleeps 30&ndash;60 min &mdash; identify any module not shutting down.",
 "<b>Charging-system / alternator output test</b> and a <b>battery load test</b>.",
 "Confirm the <b>Intelligent Battery Sensor (IBS)</b> is connected and functioning; confirm any replacement battery was <b>coded to the BMS</b>.",
 "Confirm head-unit &amp; telematics <b>software is latest level</b> and correctly configured / coded to the VIN.",
 "Test / replace the <b>key-fob battery</b> (rule out the simple cause of the keyless fault).",
]
rows = [[Paragraph(f"{i+1}.", CELL), Paragraph(t, CELL), Paragraph("[    ]", CELL)] for i,t in enumerate(tests)]
tt = Table(rows, colWidths=[18, 462, 30])
tt.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('TOPPADDING',(0,0),(-1,-1),1.5),('BOTTOMPADDING',(0,0),(-1,-1),1.5),
                        ('LEFTPADDING',(0,0),(-1,-1),2)]))
story.append(tt)

story.append(Paragraph("Escalation", H2))
story.append(Paragraph("The fault has survived replacement of <b>two major modules</b>. Please open a case with <b>Renault Technical (RTECH)</b> for factory support. The owner has opened / will open a case with Renault UK (reference above) and is keeping a dated log of all visits.", BODY))

# Dealer response box
story.append(Spacer(1,4))
resp = Table([[Paragraph("<b>Dealer findings / actions / advisor / date:</b>", CELL)],[Paragraph("<br/><br/><br/>", CELL)]],
             colWidths=[520])
resp.setStyle(TableStyle([('BOX',(0,0),(-1,-1),0.7,MUTED),('BACKGROUND',(0,0),(-1,0),BOXBG),
                          ('LEFTPADDING',(0,0),(-1,-1),6),('TOPPADDING',(0,0),(-1,-1),4),('BOTTOMPADDING',(0,0),(-1,-1),4)]))
story.append(resp)

doc = SimpleDocTemplate("W:/YourBlinds/Renault-Trafic-Dealer-Handout.pdf",
                        pagesize=A4, leftMargin=14*mm, rightMargin=14*mm, topMargin=12*mm, bottomMargin=10*mm,
                        title="Renault Trafic - Fault Diagnostic Request", author="Owner")
doc.build(story)
print("OK")
