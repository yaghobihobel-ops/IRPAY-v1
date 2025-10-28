import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../language/language_controller.dart';
import '../../utils/custom_style.dart';

class TitleHeading1Widget extends StatelessWidget {
  TitleHeading1Widget({
    super.key,
    required this.text,
    this.textAlign,
    this.textOverflow,
    this.padding = paddingValue,
    this.opacity = 1.0,
    this.maxLines,
    this.fontSize,
    this.fontWeight,
    this.color,
  });

  final String text;
  final TextAlign? textAlign;
  final TextOverflow? textOverflow;
  final EdgeInsetsGeometry padding;
  final double opacity;
  final int? maxLines;
  final double? fontSize;
  final FontWeight? fontWeight;
  final Color? color;
  static const paddingValue = EdgeInsets.all(0.0);
  final languageController = Get.put(LanguageController());
  @override
  Widget build(BuildContext context) {
    return Obx(
      () => languageController.isLoading
          ? const Text('')
          : Opacity(
              opacity: opacity,
              child: Padding(
                padding: padding,
                child: Text(
                  languageController.getTranslation(text),
                  style: Get.isDarkMode
                      ? CustomStyle.darkHeading1TextStyle.copyWith(
                          fontSize: fontSize,
                          fontWeight: fontWeight,
                          color: color,
                        )
                      : CustomStyle.lightHeading1TextStyle.copyWith(
                          fontSize: fontSize,
                          fontWeight: fontWeight,
                          color: color,
                        ),
                  textAlign: textAlign,
                  overflow: textOverflow,
                  maxLines: maxLines,
                ),
              ),
            ),
    );
  }
}
