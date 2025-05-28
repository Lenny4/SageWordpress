// https://react.dev/learn/add-react-to-an-existing-project#using-react-for-a-part-of-your-existing-page
import React, { useImperativeHandle } from "react";
import { getTranslations } from "../../../../../functions/translations";
import { MetadataInterface } from "../../../../../interface/WordpressInterface";
import { FArtfournisseInterface } from "../../../../../interface/FArticleInterface";
import {
  getListObjectSageMetadata,
  getSageMetadata,
} from "../../../../../functions/getMetadata";
import {
  FormContentInterface,
  FormInterface,
  TableLineItemInterface,
  TriggerFormContentChanged,
} from "../../../../../interface/InputInterface";
import { AfPrincipalInput } from "../inputs/AfPrincipalInput";
import { FormInput } from "../../FormInput";
import { FormContentComponent } from "../../FormContentComponent";

let translations: any = getTranslations();

const articleMeta: MetadataInterface[] = JSON.parse(
  $("[data-sage-product]").attr("data-sage-product") ?? "null",
);

export const ArticleFournisseursComponent = React.forwardRef((props, ref) => {
  const prefix = "fArtfournisses";
  const [fArtfournisses] = React.useState<FArtfournisseInterface[]>(
    getListObjectSageMetadata(prefix, articleMeta, "ctNum"),
  );
  const [selectedCtNum, setSelectedCtNum] = React.useState<string>(() => {
    return (
      fArtfournisses.find((x) => x.afPrincipal.toString() === "1")?.ctNum ?? ""
    );
  });
  const onAfPrincipalChanged: TriggerFormContentChanged = (name, newValue) => {
    setSelectedCtNum(newValue);
  };

  const getForm = (): FormInterface => {
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
                            selectedCtNum={selectedCtNum}
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
                          initValues: {
                            value: getSageMetadata(
                              prefix +
                                "[" +
                                fArtclient.ctNum +
                                "].afRefFourniss",
                              articleMeta,
                              fArtclient.afRefFourniss,
                            ),
                          },
                        },
                      },
                      {
                        field: {
                          name: prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                          DomField: FormInput,
                          hideLabel: true,
                          initValues: {
                            value: getSageMetadata(
                              prefix + "[" + fArtclient.ctNum + "].afPrixAch",
                              articleMeta,
                              fArtclient.afPrixAch,
                            ),
                          },
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
  };
  const [form, setForm] = React.useState<FormInterface>(getForm());

  useImperativeHandle(ref, () => ({
    getForm() {
      return form;
    },
  }));

  React.useEffect(() => {
    setForm(getForm());
  }, [selectedCtNum]); // eslint-disable-line react-hooks/exhaustive-deps

  return (
    <FormContentComponent content={form.content} transPrefix="fArticles" />
  );
});
