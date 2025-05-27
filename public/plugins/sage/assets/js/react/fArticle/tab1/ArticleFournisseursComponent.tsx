// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../interface/InputInterface";
import { FormContentComponent } from "../../component/form/FormContentComponent";
import { getListObjectSageMetadata } from "../../../functions/getMetadata";
import { getTranslations } from "../../../functions/translations";
import { FormInput } from "../../component/form/FormInput";
import { FArtfournisseInterface } from "../../../interface/FArticleInterface";
import { MetadataInterface } from "../../../interface/WordpressInterface";
import { AfPrincipalInput } from "../../component/form/fArticle/AfPrincipalInput";
import { getDomsToSetParentFormData } from "../../../functions/form";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtfournisses";
  const [selectedCtNum, setSelectedCtNum] = React.useState<string>(() => {
    const fArtfournisses: FArtfournisseInterface[] = getListObjectSageMetadata(
      prefix,
      articleMeta,
      "ctNum",
    );
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  });
  const [fArtfournisses] = React.useState<FArtfournisseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "ctNum"),
  );

  const onAfPrincipalChanged: TriggerFormContentChanged = (name, newValue) => {
    const doms = getDomsToSetParentFormData(form.content);
    for (const dom of doms) {
      if (dom.ref.current?.onAfPrincipalChanged) {
        dom.ref.current.onAfPrincipalChanged(newValue);
      }
    }
  };

  const [form] = React.useState<FormInterface>(() => {
    const formContent: FormContentInterface[] = [
      {
        props: {
          container: true,
          spacing: 1,
          sx: { p: 1 },
        },
        children: [
          {
            props: {
              size: { xs: 12 },
            },
            table: {
              headers: [
                translations.words.supplier,
                translations.words.main,
                translations.words.supplierRef,
                translations.words.buyPrice,
              ],
              items: fArtfournisses.map(
                (fArtclient): TableLineItemInterface => {
                  return {
                    item: fArtclient,
                    lines: [
                      {
                        Dom: <>{fArtclient.ctNum}</>,
                      },
                      {
                        Dom: (
                          <AfPrincipalInput
                            defaultCtNum={selectedCtNum}
                            ctNum={fArtclient.ctNum}
                            onAfPrincipalChangedParent={onAfPrincipalChanged}
                            ref={React.createRef()}
                          />
                        ),
                      },
                      {
                        field: {
                          name:
                            prefix + "[" + fArtclient.ctNum + "].afRefFourniss",
                          DomField: FormInput,
                          hideLabel: true,
                          initValues: { value: fArtclient.afRefFourniss },
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          hideLabel: true,
                          initValues: { value: fArtclient.afPrixAch },
                        },
                      },
                    ],
                  };
                },
              ),
            },
          },
        ],
      },
    ];
    return {
      content: formContent,
    };
  });

  useImperativeHandle(ref, () => ({
    getForm() {
      return form;
    },
  }));

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
